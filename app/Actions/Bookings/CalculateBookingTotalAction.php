<?php

namespace App\Actions\Bookings;

use App\Actions\ComputeMealQuoteAction;
use App\Models\Room;
use App\Models\Promo;
use App\Services\PromoCalculationService;

class CalculateBookingTotalAction
{
    public function __construct(
        private ComputeMealQuoteAction $computeMealQuoteAction,
        private PromoCalculationService $promoCalculationService
    ) {}

    public function execute(array $bookingRoomArr, string $check_in_date, string $check_out_date, int $adults, int $children, ?Promo $promo = null): array
    {
        $roomIds = array_unique(array_map(fn($r) => $r->room_id, $bookingRoomArr));
        $rooms = Room::whereIn('slug', $roomIds)->get()->keyBy('slug');

        $totalRoom = 0;
        $nights = \Carbon\Carbon::parse($check_in_date)->diffInDays($check_out_date);
        foreach ($bookingRoomArr as $roomData) {
            $room = $rooms[$roomData->room_id];
            $totalRoom += $room->price_per_night * $nights;
        }

        // Get meal program info for the stay (dates only)
        $mealQuote = $this->computeMealQuoteAction->execute($check_in_date, $check_out_date);
        
        // Calculate meal total based on actual guest counts and room capacity
        $mealTotal = $this->calculateMealTotalForBooking($mealQuote, $bookingRoomArr, $rooms);
        
        // Calculate extra guest fees for buffet days
        $extraGuestData = $this->calculateExtraGuestFees($mealQuote, $bookingRoomArr, $rooms);

        // Update the meal quote with calculated totals for email/PDF templates
        $mealQuote->mealSubtotal = $mealTotal;

        $finalTotal = $totalRoom + $mealTotal + $extraGuestData['total_fee'];
        
        $totals = [
            'total_room' => $totalRoom,
            'meal_total' => $mealTotal,
            'extra_guest_fee' => $extraGuestData['total_fee'],
            'extra_guest_count' => $extraGuestData['total_count'],
            'final_price' => $finalTotal,
            'meal_quote' => $mealQuote // Include meal quote for email templates
        ];

        // Calculate promo discount if promo is provided
        if ($promo) {
            $promoResult = $this->calculatePromoDiscount($promo, $check_in_date, $check_out_date, $totals, $bookingRoomArr, $rooms->toArray());
            $totals['promo_discount'] = $promoResult;
        }

        return $totals;
    }

    /**
     * Calculate promo discount using the PromoCalculationService
     *
     * @param Promo $promo
     * @param string $checkInDate
     * @param string $checkOutDate
     * @param array $totals
     * @param array $bookingRoomArr
     * @param array $rooms
     * @return array|null
     */
    private function calculatePromoDiscount(Promo $promo, string $checkInDate, string $checkOutDate, array $totals, array $bookingRoomArr, array $rooms): ?array
    {
        // Validate promo for the date range first
        $validation = $this->promoCalculationService->validatePromoForDateRange($promo, $checkInDate, $checkOutDate);
        
        if (!$validation['is_valid']) {
            return null; // Promo is not valid for this booking
        }

        // Calculate the discount
        $discountResult = $this->promoCalculationService->calculateDiscount(
            $promo,
            $checkInDate,
            $checkOutDate,
            $totals,
            $bookingRoomArr,
            $rooms
        );

        return $discountResult;
    }

    /**
     * Calculate meal total for booking based on room guests and meal program
     */
    private function calculateMealTotalForBooking($mealQuote, array $bookingRoomArr, $rooms): float
    {
        $totalMealCost = 0;

        foreach ($mealQuote->nights as $night) {
            $nightCost = 0;

            foreach ($bookingRoomArr as $roomData) {
                $room = $rooms[$roomData->room_id] ?? null;
                if (!$room) {
                    continue; // Skip if room not found
                }
                $adults = $roomData->adults ?? 0;
                $children = $roomData->children ?? 0;

                if ($night->type === 'buffet') {
                    // Buffet: ALL guests (including extra guests) pay the buffet meal price
                    $totalGuests = $adults + $children;
                    $nightCost += ($adults * ($night->adultPrice ?? 0)) + ($children * ($night->childPrice ?? 0));
                } else {
                    // Free breakfast: only extra guests pay for breakfast
                    $totalGuests = $adults + $children;
                    $extraGuests = max(0, $totalGuests - $room->max_guests);
                    
                    // Use adult breakfast price for extra guests
                    $nightCost += $extraGuests * ($night->adultBreakfastPrice ?? 0);
                }
            }

            $totalMealCost += $nightCost;
        }

        return round($totalMealCost, 2);
    }

    /**
     * Calculate extra guest fees for buffet days only
     */
    private function calculateExtraGuestFees($mealQuote, array $bookingRoomArr, $rooms): array
    {
        $totalExtraGuestFee = 0;
        $totalExtraGuestCount = 0;

        foreach ($mealQuote->nights as $night) {
            // Only calculate extra guest fees for buffet days
            if ($night->type === 'buffet' && $night->extraGuestFee > 0) {
                $nightExtraGuestCount = 0;
                $nightExtraGuestFee = 0;

                foreach ($bookingRoomArr as $roomData) {
                    $room = $rooms[$roomData->room_id] ?? null;
                    if (!$room) {
                        continue; // Skip if room not found
                    }
                    $adults = $roomData->adults ?? 0;
                    $children = $roomData->children ?? 0;
                    $totalGuests = $adults + $children;
                    
                    // Calculate extra guests for this room
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
