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

    /**
     * Meal cost = buffet nights only (all guests pay the buffet rate).
     * Free-breakfast nights have no meal cost; the extra-guest charge on those
     * nights is an extra guest fee (see calculateExtraGuestFees).
     */
    private function calculateMealTotalForBooking($mealQuote, array $bookingRoomArr, $rooms, ?Promo $promo = null): float
    {
        $totalMealCost = 0;

        foreach ($mealQuote->nights as $night) {
            if ($night->type !== 'buffet') {
                continue;
            }

            foreach ($bookingRoomArr as $roomData) {
                $room = $rooms[$roomData->room_id] ?? null;
                if (!$room) {
                    continue;
                }
                $adults = $roomData->adults ?? 0;
                $children = $roomData->children ?? 0;

                $totalMealCost += ($adults * ($night->adultPrice ?? 0)) + ($children * ($night->childPrice ?? 0));
            }
        }

        return round($totalMealCost, 2);
    }

    /**
     * Extra guest fee (breakfast, amenities and related services) for guests beyond room capacity.
     * Rate per extra guest per night comes from the meal pricing tier:
     * - buffet nights: extra_guest_fee
     * - free-breakfast nights: adult_breakfast_price
     *
     * total_count is the number of extra guests (not guest-nights).
     */
    private function calculateExtraGuestFees($mealQuote, array $bookingRoomArr, $rooms): array
    {
        $totalExtraGuestFee = 0;
        $totalExtraGuestCount = 0;

        foreach ($mealQuote->nights as $night) {
            $rate = $night->type === 'buffet' ? ($night->extraGuestFee ?? 0) : ($night->adultBreakfastPrice ?? 0);
            if ($rate <= 0) {
                continue;
            }

            $nightExtraGuestCount = 0;

            foreach ($bookingRoomArr as $roomData) {
                $room = $rooms[$roomData->room_id] ?? null;
                if (!$room) {
                    continue;
                }
                $adults = $roomData->adults ?? 0;
                $children = $roomData->children ?? 0;
                $nightExtraGuestCount += max(0, ($adults + $children) - $room->max_guests);
            }

            $totalExtraGuestFee += $nightExtraGuestCount * $rate;
            $totalExtraGuestCount = max($totalExtraGuestCount, $nightExtraGuestCount);
        }

        return [
            'total_fee' => round($totalExtraGuestFee, 2),
            'total_count' => $totalExtraGuestCount
        ];
    }
}
