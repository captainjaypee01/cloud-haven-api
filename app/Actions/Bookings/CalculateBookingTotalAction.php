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
        $mealTotal = $this->calculateMealTotalForBooking($mealQuote, $bookingRoomArr, $rooms, $promo);
        
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
            $promoResult = $this->calculatePromoDiscount($promo, $check_in_date, $check_out_date, $totals, $bookingRoomArr, $rooms->toArray(), $mealQuote);
            $totals['promo_discount'] = $promoResult;
        }

        return $totals;
    }

    /**
     * Calculate promo discount using the PromoCalculationService
     * Now handles per-night calculations with excluded days
     *
     * @param Promo $promo
     * @param string $checkInDate
     * @param string $checkOutDate
     * @param array $totals
     * @param array $bookingRoomArr
     * @param array $rooms
     * @param object|null $mealQuote
     * @return array|null
     */
    private function calculatePromoDiscount(Promo $promo, string $checkInDate, string $checkOutDate, array $totals, array $bookingRoomArr, array $rooms, $mealQuote = null): ?array
    {
        // Note: Validation is now handled in BookingService before calling this action
        // So we can proceed directly to calculation
        
        // Calculate the discount using PromoCalculationService
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
     * Calculate meal total for booking based on room guests and meal program
     * Now supports per-night promo calculations
     */
    private function calculateMealTotalForBooking($mealQuote, array $bookingRoomArr, $rooms, ?Promo $promo = null): float
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
                    
                    // Calculate buffet cost per pax and apply discount per pax on eligible nights
                    if ($promo && $promo->scope === 'meal') {
                        // Apply discount per pax for buffet meals
                        $discountedAdultPrice = $this->applyMealPromoDiscountPerPax($night->adultPrice ?? 0, $promo, $night->date);
                        $discountedChildPrice = $this->applyMealPromoDiscountPerPax($night->childPrice ?? 0, $promo, $night->date);
                        
                        $nightCost += ($adults * $discountedAdultPrice) + ($children * $discountedChildPrice);
                    } else {
                        // No discount - use regular prices
                        $nightCost += ($adults * ($night->adultPrice ?? 0)) + ($children * ($night->childPrice ?? 0));
                    }
                } else {
                    // Free breakfast: only extra guests pay for breakfast
                    $totalGuests = $adults + $children;
                    $extraGuests = max(0, $totalGuests - $room->max_guests);
                    
                    // Use adult breakfast price for extra guests
                    $baseCost = $extraGuests * ($night->adultBreakfastPrice ?? 0);
                    
                    // Apply promo discount if applicable (meal scope only)
                    if ($promo && $promo->scope === 'meal') {
                        $discountedCost = $this->applyMealPromoDiscount($baseCost, $promo, $night->date);
                        $nightCost += $discountedCost;
                    } else {
                        $nightCost += $baseCost;
                    }
                }
            }

            $totalMealCost += $nightCost;
        }

        return round($totalMealCost, 2);
    }

    /**
     * Apply promo discount to meal cost for a specific night
     * Handles per-night calculation with excluded days
     */
    private function applyMealPromoDiscount(float $baseCost, Promo $promo, \Carbon\Carbon $mealDate): float
    {
        // Check if this night is eligible for promo (not excluded)
        $dayOfWeek = $mealDate->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
        
        if ($promo->excluded_days && in_array($dayOfWeek, $promo->excluded_days)) {
            // This day is excluded from promo - no discount
            return $baseCost;
        }
        
        // Apply discount based on promo type
        if ($promo->discount_type === 'percentage') {
            $discount = $baseCost * ($promo->discount_value / 100);
            return $baseCost - $discount;
        } else if ($promo->discount_type === 'fixed') {
            $discount = min($promo->discount_value, $baseCost);
            return $baseCost - $discount;
        }
        
        return $baseCost;
    }

    /**
     * Apply promo discount per pax for buffet meals
     * Handles per-night calculation with excluded days for individual pricing
     */
    private function applyMealPromoDiscountPerPax(float $pricePerPax, Promo $promo, \Carbon\Carbon $mealDate): float
    {
        // Check if this night is eligible for promo (not excluded)
        $dayOfWeek = $mealDate->dayOfWeek; // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
        
        if ($promo->excluded_days && in_array($dayOfWeek, $promo->excluded_days)) {
            // This day is excluded from promo - no discount
            return $pricePerPax;
        }
        
        // Apply discount based on promo type
        if ($promo->discount_type === 'percentage') {
            $discount = $pricePerPax * ($promo->discount_value / 100);
            return $pricePerPax - $discount;
        } else if ($promo->discount_type === 'fixed') {
            // For fixed discount, we need to be careful - we can't discount more than the price per pax
            $discount = min($promo->discount_value, $pricePerPax);
            return $pricePerPax - $discount;
        }
        
        return $pricePerPax;
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
