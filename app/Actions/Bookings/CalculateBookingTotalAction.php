<?php

namespace App\Actions\Bookings;

use App\Actions\ComputeMealQuoteAction;
use App\Contracts\Services\RoomPricingServiceInterface;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Promo;
use App\Services\PromoCalculationService;
use App\Services\RoomQuoteSnapshotService;

class CalculateBookingTotalAction
{
    public function __construct(
        private ComputeMealQuoteAction $computeMealQuoteAction,
        private PromoCalculationService $promoCalculationService,
        private RoomPricingServiceInterface $roomPricingService,
        private RoomQuoteSnapshotService $roomQuoteSnapshotService,
    ) {}

    public function execute(
        array $bookingRoomArr,
        string $check_in_date,
        string $check_out_date,
        int $adults,
        int $children,
        ?Promo $promo = null,
        ?Booking $booking = null,
    ): array {
        $roomIds = array_unique(array_map(fn ($r) => $r->room_id, $bookingRoomArr));
        $rooms = Room::whereIn('slug', $roomIds)->get()->keyBy('slug');

        $snapshot = $booking ? $this->roomQuoteSnapshotService->parseSnapshot($booking) : null;
        $roomQuote = $this->roomQuoteSnapshotService->buildForStay(
            $snapshot,
            $rooms->all(),
            $bookingRoomArr,
            $check_in_date,
            $check_out_date,
        );

        $totalRoom = $roomQuote->totalRoom;

        $mealQuote = $this->computeMealQuoteAction->execute($check_in_date, $check_out_date);
        
        $mealTotal = $this->calculateMealTotalForBooking($mealQuote, $bookingRoomArr, $rooms, $promo);
        
        $extraGuestData = $this->calculateExtraGuestFees($mealQuote, $bookingRoomArr, $rooms);

        $mealQuote->mealSubtotal = $mealTotal;

        $finalTotal = $totalRoom + $mealTotal + $extraGuestData['total_fee'];
        
        $totals = [
            'total_room' => $totalRoom,
            'meal_total' => $mealTotal,
            'extra_guest_fee' => $extraGuestData['total_fee'],
            'extra_guest_count' => $extraGuestData['total_count'],
            'final_price' => $finalTotal,
            'meal_quote' => $mealQuote,
            'room_quote' => $roomQuote,
        ];

        if ($promo) {
            $promoResult = $this->calculatePromoDiscount($promo, $check_in_date, $check_out_date, $totals, $bookingRoomArr, $rooms->toArray(), $mealQuote);
            $totals['promo_discount'] = $promoResult;
        }

        return $totals;
    }

    private function calculatePromoDiscount(Promo $promo, string $checkInDate, string $checkOutDate, array $totals, array $bookingRoomArr, array $rooms, $mealQuote = null): ?array
    {
        $discountResult = $this->promoCalculationService->calculateDiscount(
            $promo,
            $checkInDate,
            $checkOutDate,
            $totals,
            $bookingRoomArr,
            $rooms,
            $mealQuote
        );

        return $discountResult;
    }

    private function calculateMealTotalForBooking($mealQuote, array $bookingRoomArr, $rooms, ?Promo $promo = null): float
    {
        $totalMealCost = 0;

        foreach ($mealQuote->nights as $night) {
            $nightCost = 0;

            foreach ($bookingRoomArr as $roomData) {
                $room = $rooms[$roomData->room_id] ?? null;
                if (!$room) {
                    continue;
                }
                $adults = $roomData->adults ?? 0;
                $children = $roomData->children ?? 0;

                if ($night->type === 'buffet') {
                    $nightCost += ($adults * ($night->adultPrice ?? 0)) + ($children * ($night->childPrice ?? 0));
                } else {
                    $totalGuests = $adults + $children;
                    $extraGuests = max(0, $totalGuests - $room->max_guests);
                    $baseCost = $extraGuests * ($night->adultBreakfastPrice ?? 0);
                    $nightCost += $baseCost;
                }
            }

            $totalMealCost += $nightCost;
        }

        return round($totalMealCost, 2);
    }

    private function calculateExtraGuestFees($mealQuote, array $bookingRoomArr, $rooms): array
    {
        $totalExtraGuestFee = 0;
        $totalExtraGuestCount = 0;

        foreach ($mealQuote->nights as $night) {
            if ($night->type === 'buffet' && $night->extraGuestFee > 0) {
                $nightExtraGuestCount = 0;
                $nightExtraGuestFee = 0;

                foreach ($bookingRoomArr as $roomData) {
                    $room = $rooms[$roomData->room_id] ?? null;
                    if (!$room) {
                        continue;
                    }
                    $adults = $roomData->adults ?? 0;
                    $children = $roomData->children ?? 0;
                    $totalGuests = $adults + $children;
                    $extraGuestsInRoom = max(0, $totalGuests - $room->max_guests);
                    
                    if ($extraGuestsInRoom > 0) {
                        $nightExtraGuestCount += $extraGuestsInRoom;
                        $nightExtraGuestFee += $extraGuestsInRoom * $night->extraGuestFee;
                    }
                }

                $totalExtraGuestCount += $nightExtraGuestCount;
                $totalExtraGuestFee += $nightExtraGuestFee;
            }
        }

        return [
            'total_fee' => round($totalExtraGuestFee, 2),
            'total_count' => $totalExtraGuestCount
        ];
    }
}
