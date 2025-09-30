<?php

namespace App\Services;

use App\Models\Promo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PromoCalculationService
{
    /**
     * Calculate discount for a booking with per-night logic
     *
     * @param Promo $promo
     * @param string $checkInDate
     * @param string $checkOutDate
     * @param array $totals Array containing 'total_room', 'meal_total', 'final_price'
     * @param array $bookingRoomArr Array of booking room data
     * @param array $rooms Array of room models keyed by slug
     * @param object|null $mealQuote Optional meal quote object with detailed breakdown
     * @return array
     */
    public function calculateDiscount(
        Promo $promo,
        string $checkInDate,
        string $checkOutDate,
        array $totals,
        array $bookingRoomArr = [],
        array $rooms = [],
        $mealQuote = null
    ): array {
        // If promo doesn't use per-night calculation, use traditional logic
        if (!$promo->usesPerNightCalculation()) {
            return $this->calculateTraditionalDiscount($promo, $totals);
        }

        // Calculate per-night discount
        return $this->calculatePerNightDiscount($promo, $checkInDate, $checkOutDate, $totals, $bookingRoomArr, $rooms, $mealQuote);
    }

    /**
     * Calculate traditional discount (entire booking)
     *
     * @param Promo $promo
     * @param array $totals
     * @return array
     */
    private function calculateTraditionalDiscount(Promo $promo, array $totals): array
    {
        $baseAmount = $this->getBaseAmount($promo, $totals);
        $discountAmount = $this->calculateDiscountAmount($promo, $baseAmount);

        return [
            'discount_amount' => $discountAmount,
            'eligible_nights' => 1, // Traditional calculation treats entire booking as one unit
            'total_nights' => 1,
            'per_night_breakdown' => [
                [
                    'date' => null,
                    'eligible' => true,
                    'discount_amount' => $discountAmount,
                    'base_amount' => $baseAmount
                ]
            ]
        ];
    }

    /**
     * Calculate per-night discount
     *
     * @param Promo $promo
     * @param string $checkInDate
     * @param string $checkOutDate
     * @param array $totals
     * @param array $bookingRoomArr
     * @param array $rooms
     * @param object|null $mealQuote
     * @return array
     */
    private function calculatePerNightDiscount(
        Promo $promo,
        string $checkInDate,
        string $checkOutDate,
        array $totals,
        array $bookingRoomArr,
        array $rooms,
        $mealQuote = null
    ): array {
        $checkIn = Carbon::parse($checkInDate);
        $checkOut = Carbon::parse($checkOutDate);
        $nights = $checkIn->diffInDays($checkOut);


        $perNightBreakdown = [];
        $totalDiscountAmount = 0;
        $eligibleNights = 0;

        // Calculate per-night amounts
        $perNightAmounts = $this->calculatePerNightAmounts($totals, $nights, $bookingRoomArr, $rooms, $mealQuote);

        for ($i = 0; $i < $nights; $i++) {
            $currentDate = $checkIn->copy()->addDays($i);
            $isEligible = $promo->isDateEligible($currentDate);
            
            // Get the base amount based on promo scope
            $nightAmounts = $perNightAmounts[$i] ?? ['room' => 0, 'meal' => 0, 'total' => 0];
            $baseAmount = $this->getBaseAmountForNight($promo, $nightAmounts);
            
            $nightData = [
                'date' => $currentDate->format('Y-m-d'),
                'day_of_week' => $currentDate->dayOfWeek,
                'day_name' => $currentDate->format('l'),
                'eligible' => $isEligible,
                'base_amount' => $baseAmount,
                'discount_amount' => 0
            ];

            if ($isEligible) {
                $eligibleNights++;
                $nightData['discount_amount'] = $this->calculateDiscountAmount($promo, $baseAmount);
                $totalDiscountAmount += $nightData['discount_amount'];
            }

            $perNightBreakdown[] = $nightData;
        }

        return [
            'discount_amount' => round($totalDiscountAmount, 2),
            'eligible_nights' => $eligibleNights,
            'total_nights' => $nights,
            'per_night_breakdown' => $perNightBreakdown
        ];
    }

    /**
     * Calculate per-night amounts for room, meal, and total
     *
     * @param array $totals
     * @param int $nights
     * @param array $bookingRoomArr
     * @param array $rooms
     * @param object|null $mealQuote
     * @return array
     */
    private function calculatePerNightAmounts(array $totals, int $nights, array $bookingRoomArr, array $rooms, $mealQuote = null): array
    {
        $perNightAmounts = [];

        // Calculate per-night room cost
        $perNightRoom = $totals['total_room'] / $nights;
        
        // Calculate per-night total
        $perNightTotal = $totals['final_price'] / $nights;

        for ($i = 0; $i < $nights; $i++) {
            $perNightMeal = 0;
            
            // Calculate actual meal cost for this specific night based on guest counts and room data
            if ($mealQuote && isset($mealQuote->nights) && is_array($mealQuote->nights) && isset($mealQuote->nights[$i])) {
                $mealNight = $mealQuote->nights[$i];
                
                // Calculate actual meal cost for this night based on guest counts
                if ($mealNight->type === 'buffet' && $mealNight->adultPrice !== null && $mealNight->childPrice !== null) {
                    // For buffet nights, calculate cost based on actual guest counts
                    $totalAdults = 0;
                    $totalChildren = 0;
                    
                    foreach ($bookingRoomArr as $roomData) {
                        $totalAdults += $roomData->adults ?? 0;
                        $totalChildren += $roomData->children ?? 0;
                    }
                    
                    $perNightMeal = ($totalAdults * $mealNight->adultPrice) + ($totalChildren * $mealNight->childPrice);
                } else if ($mealNight->type === 'free_breakfast' && $mealNight->adultBreakfastPrice !== null) {
                    // For free breakfast nights, calculate extra guest breakfast fees
                    $totalExtraGuests = 0;
                    
                    foreach ($bookingRoomArr as $roomData) {
                        $roomAdults = $roomData->adults ?? 0;
                        $roomChildren = $roomData->children ?? 0;
                        $roomMaxGuests = $rooms[$roomData->room_id]->max_guests ?? 2;
                        $totalGuestsInRoom = $roomAdults + $roomChildren;
                        
                        if ($totalGuestsInRoom > $roomMaxGuests) {
                            $totalExtraGuests += $totalGuestsInRoom - $roomMaxGuests;
                        }
                    }
                    
                    $perNightMeal = $totalExtraGuests * $mealNight->adultBreakfastPrice;
                }
            } else {
                // Fallback to simple division if no meal quote data
                $perNightMeal = $totals['meal_total'] / $nights;
            }

            $perNightAmounts[$i] = [
                'room' => round($perNightRoom, 2),
                'meal' => round($perNightMeal, 2),
                'total' => round($perNightTotal, 2)
            ];
        }

        return $perNightAmounts;
    }

    /**
     * Get the base amount for discount calculation based on promo scope
     *
     * @param Promo $promo
     * @param array $totals
     * @return float
     */
    private function getBaseAmount(Promo $promo, array $totals): float
    {
        switch ($promo->scope) {
            case 'room':
                return $totals['total_room'];
            case 'meal':
                return $totals['meal_total'];
            case 'total':
            default:
                return $totals['final_price'];
        }
    }

    /**
     * Get the base amount for a specific night based on promo scope
     *
     * @param Promo $promo
     * @param array $nightAmounts
     * @return float
     */
    private function getBaseAmountForNight(Promo $promo, array $nightAmounts): float
    {
        switch ($promo->scope) {
            case 'room':
                return $nightAmounts['room'];
            case 'meal':
                return $nightAmounts['meal'];
            case 'total':
            default:
                return $nightAmounts['total'];
        }
    }

    /**
     * Calculate discount amount based on promo type and value
     *
     * @param Promo $promo
     * @param float $baseAmount
     * @return float
     */
    private function calculateDiscountAmount(Promo $promo, float $baseAmount): float
    {
        if ($promo->discount_type === 'percentage') {
            return round($baseAmount * ($promo->discount_value / 100), 2);
        } else {
            return round(min($promo->discount_value, $baseAmount), 2);
        }
    }

    /**
     * Validate if promo is applicable for the given date range
     *
     * @param Promo $promo
     * @param string $checkInDate
     * @param string $checkOutDate
     * @return array
     */
    public function validatePromoForDateRange(Promo $promo, string $checkInDate, string $checkOutDate): array
    {
        $checkIn = Carbon::parse($checkInDate);
        $checkOut = Carbon::parse($checkOutDate);
        
        // Check if promo period overlaps with booking dates
        $promoStart = $promo->starts_at ? $promo->starts_at->startOfDay() : null;
        $promoEnd = $promo->ends_at ? $promo->ends_at->startOfDay() : null;
        
        $isValid = true;
        $errors = [];
        
        // Check if booking period overlaps with promo period
        // Booking overlaps if: booking starts before promo ends AND booking ends after promo starts
        if ($promoStart && $promoEnd) {
            // Check if booking period overlaps with promo period
            if ($checkOut->lte($promoStart) || $checkIn->gte($promoEnd)) {
                $isValid = false;
                $errors[] = 'Booking period does not overlap with promo period.';
            }
        } elseif ($promoStart) {
            // Only start date is set - check if booking ends after promo starts
            if ($checkOut->lte($promoStart)) {
                $isValid = false;
                $errors[] = 'Booking period does not overlap with promo period.';
            }
        } elseif ($promoEnd) {
            // Only end date is set - check if booking starts before promo ends
            if ($checkIn->gte($promoEnd)) {
                $isValid = false;
                $errors[] = 'Booking period does not overlap with promo period.';
            }
        }
        
        // If using per-night calculation, check if any nights are eligible
        if ($promo->usesPerNightCalculation()) {
            $hasEligibleNights = false;
            $nights = $checkIn->diffInDays($checkOut);
            
            for ($i = 0; $i < $nights; $i++) {
                $currentDate = $checkIn->copy()->addDays($i);
                if ($promo->isDateEligible($currentDate)) {
                    $hasEligibleNights = true;
                    break;
                }
            }
            
            if (!$hasEligibleNights) {
                $isValid = false;
                $errors[] = 'No nights in the booking period are eligible for this promo.';
            }
        }
        
        return [
            'is_valid' => $isValid,
            'errors' => $errors
        ];
    }
}
