<?php

namespace App\Services;

use App\Contracts\Repositories\MealPricingTierRepositoryInterface;
use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\Contracts\Services\MealPricingServiceInterface;
use App\DTO\DayTour\DayTourMealBreakdownDTO;
use App\DTO\DayTour\DayTourMealLineItemDTO;
use App\DTO\MealNightDTO;
use App\DTO\MealQuoteDTO;
use App\Models\MealProgram;
use App\Models\MealPricingTier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MealPricingService implements MealPricingServiceInterface
{
    public function __construct(
        private MealCalendarServiceInterface $calendarService,
        private MealProgramRepositoryInterface $programRepository,
        private MealPricingTierRepositoryInterface $pricingTierRepository
    ) {}

    /**
     * Get meal program information for a stay (simplified approach)
     * Returns only meal program info and pricing, no calculations
     * 
     * IMPORTANT: Each night represents the BREAKFAST for the next day
     * Oct 18-19 (1 night) = breakfast on Oct 19
     * Oct 15-17 (2 nights) = breakfast on Oct 16 and Oct 17
     */
    public function getMealProgramInfoForStay(Carbon $checkIn, Carbon $checkOut): MealQuoteDTO
    {
        $nights = [];
        
        // Get property timezone from config
        $timezone = config('resort.timezone', 'Asia/Singapore');
        
        // Iterate breakfast days (check-in + 1 day to check-out)
        $current = $checkIn->copy()->setTimezone($timezone)->startOfDay()->addDay(); // Start from next day
        $end = $checkOut->copy()->setTimezone($timezone)->startOfDay();
        while ($current->lte($end)) {
            // Find the active meal program for this breakfast date
            $program = $this->findActiveProgramForDate($current);
            if ($program) {
                // Check if buffet is active on this breakfast date
                $isBuffetActive = $this->calendarService->isBuffetActiveOn($current);
                
                // Get pricing tier for this date
                $tier = $this->getPricingTierForDate($program->id, $current);
                if ($tier) {
                    $nights[] = new MealNightDTO(
                        date: $current->copy(),
                        type: $isBuffetActive ? 'buffet' : 'free_breakfast',
                        adultPrice: $isBuffetActive ? (float) $tier->adult_price : null,
                        childPrice: $isBuffetActive ? (float) $tier->child_price : null,
                        adults: 0, // No calculations, just program info
                        children: 0,
                        nightTotal: 0.0,
                        adultBreakfastPrice: (float) ($tier->adult_breakfast_price ?? 0),
                        childBreakfastPrice: (float) ($tier->child_breakfast_price ?? 0),
                        extraAdults: 0,
                        extraChildren: 0,
                        breakfastTotal: 0.0
                    );
                } else {
                    // No pricing tier found, but program exists
                    $nights[] = new MealNightDTO(
                        date: $current->copy(),
                        type: 'free_breakfast',
                        adults: 0,
                        children: 0,
                        nightTotal: 0.0
                    );
                }
            } else {
                // No active program for this date
                $nights[] = new MealNightDTO(
                    date: $current->copy(),
                    type: 'free_breakfast',
                    adults: 0,
                    children: 0,
                    nightTotal: 0.0
                );
            }
            
            $current->addDay();
        }
        // Get any active program for labels
        $anyProgram = $this->getActiveMealProgram();
        $labels = [
            'inactive' => $anyProgram ? $anyProgram->inactive_label : 'Complimentary Breakfast Only'
        ];
        
        return new MealQuoteDTO(
            nights: $nights,
            mealSubtotal: 0.0, // No calculations in simplified API
            labels: $labels
        );
    }

    /**
     * Original method for backward compatibility and internal use
     */
    public function quoteForStay(Carbon $checkIn, Carbon $checkOut, int $adults, int $children, ?array $rooms = null): MealQuoteDTO
    {
        $nights = [];
        $mealSubtotal = 0.0;
        
        // Get property timezone from config
        $timezone = config('resort.timezone', 'Asia/Singapore');
        
        // Iterate breakfast days (check-in + 1 day to check-out)
        // Same logic as simplified method: breakfast is for the next day
        $current = $checkIn->copy()->setTimezone($timezone)->startOfDay()->addDay();
        $end = $checkOut->copy()->setTimezone($timezone)->startOfDay();
        
        while ($current->lte($end)) {
            $isBuffetActive = $this->calendarService->isBuffetActiveOn($current);
            $program = $this->calendarService->getActiveProgramForDate($current);
            
            if ($isBuffetActive && $program) {
                // Get pricing tier for this date
                $tier = $this->getPricingTierForDate($program->id, $current);
                
                if ($tier) {
                    $nightTotal = ($adults * $tier->adult_price) + ($children * $tier->child_price);
                    
                    $nights[] = new MealNightDTO(
                        date: $current->copy(),
                        type: 'buffet',
                        adultPrice: (float) $tier->adult_price,
                        childPrice: (float) $tier->child_price,
                        adults: $adults,
                        children: $children,
                        nightTotal: $nightTotal
                    );
                    
                    $mealSubtotal += $nightTotal;
                } else {
                    // No pricing tier found, default to free breakfast
                    $nights[] = new MealNightDTO(
                        date: $current->copy(),
                        type: 'free_breakfast',
                        adults: $adults,
                        children: $children,
                        nightTotal: 0.0
                    );
                }
            } else {
                // Buffet not active - calculate breakfast costs for extra guests
                $breakfastTotal = 0.0;
                $extraAdults = 0;
                $extraChildren = 0;
                $adultBreakfastPrice = null;
                $childBreakfastPrice = null;

                // For breakfast pricing, look for any program that covers this date
                // (even if buffet is not active due to weekly patterns)
                $breakfastProgram = $this->findProgramForBreakfastPricing($current);

                if ($rooms && $breakfastProgram) {
                    $tier = $this->getPricingTierForDate($breakfastProgram->id, $current);
                    
                    if ($tier && $tier->adult_breakfast_price !== null) {
                        // Calculate total extra guests across all rooms
                        $totalExtraGuests = 0;
                        
                        foreach ($rooms as $roomData) {
                            $roomAdults = $roomData['adults'] ?? 0;
                            $roomChildren = $roomData['children'] ?? 0;
                            $roomMaxGuests = $roomData['max_guests'] ?? 2;
                            $totalGuestsInRoom = $roomAdults + $roomChildren;
                            
                            if ($totalGuestsInRoom > $roomMaxGuests) {
                                $extraGuestsInRoom = $totalGuestsInRoom - $roomMaxGuests;
                                $totalExtraGuests += $extraGuestsInRoom;
                            }
                        }
                        
                        // Use adult breakfast price for all extra guests (simplified)
                        $adultBreakfastPrice = (float) $tier->adult_breakfast_price;
                        $childBreakfastPrice = (float) ($tier->child_breakfast_price ?? $tier->adult_breakfast_price);
                        $breakfastTotal = $totalExtraGuests * $adultBreakfastPrice;
                        
                        // For display purposes, show all extras as "extra adults"
                        $extraAdults = $totalExtraGuests;
                        $extraChildren = 0;
                    }
                }

                $nights[] = new MealNightDTO(
                    date: $current->copy(),
                    type: 'free_breakfast',
                    adults: $adults,
                    children: $children,
                    nightTotal: $breakfastTotal,
                    adultBreakfastPrice: $adultBreakfastPrice,
                    childBreakfastPrice: $childBreakfastPrice,
                    extraAdults: $extraAdults,
                    extraChildren: $extraChildren,
                    breakfastTotal: $breakfastTotal
                );
                
                $mealSubtotal += $breakfastTotal;
            }
            
            $current->addDay();
        }
        
        // Get the first program we can find to get the inactive label
        $anyProgram = $this->getActiveMealProgram();
        $labels = [
            'inactive' => $anyProgram ? $anyProgram->inactive_label : 'Complimentary Breakfast Only'
        ];
        
        return new MealQuoteDTO(
            nights: $nights,
            mealSubtotal: $mealSubtotal,
            labels: $labels
        );
    }

    public function getActiveMealProgram(): ?MealProgram
    {
        $activePrograms = $this->programRepository->getActive();
        
        if ($activePrograms->isEmpty()) {
            return null;
        }
        
        if ($activePrograms->count() > 1) {
            Log::warning('Multiple active meal programs found. Using the most recently updated one.', [
                'program_ids' => $activePrograms->pluck('id')->toArray()
            ]);
        }
        
        return $activePrograms->first();
    }

    public function getPricingTierForDate(int $programId, Carbon $date): ?MealPricingTier
    {
        return $this->pricingTierRepository->getEffectiveTierForDate($programId, $date);
    }

    public function quoteDayTourMeals(
        Carbon $date,
        int $adults,
        int $children,
        bool $includeLunch,
        bool $includePmSnack,
        string $pmSnackPolicy
    ): DayTourMealBreakdownDTO {
        // Get property timezone
        $timezone = config('resort.timezone', 'Asia/Singapore');
        $localDate = $date->copy()->setTimezone($timezone)->startOfDay();
        
        $program = $this->calendarService->getActiveProgramForDate($localDate);
        
        // Check if buffet is active on this date
        $isBuffetActive = $this->calendarService->isBuffetActiveOn($localDate);
        
        $lunch = null;
        $pmSnack = null;
        
        if ($isBuffetActive && $program) {
            $tier = $this->getPricingTierForDate($program->id, $localDate);
            
            if ($tier) {
                // Handle lunch
                if ($includeLunch && $tier->adult_lunch_price !== null && $tier->child_lunch_price !== null) {
                    $lunchTotal = ($adults * $tier->adult_lunch_price) + ($children * $tier->child_lunch_price);
                    $lunch = new DayTourMealLineItemDTO(
                        adultPrice: (float) $tier->adult_lunch_price,
                        childPrice: (float) $tier->child_lunch_price,
                        adults: $adults,
                        children: $children,
                        total: $lunchTotal,
                        applied: true
                    );
                }
                
                // Handle PM snack based on policy
                if ($tier->adult_pm_snack_price !== null && $tier->child_pm_snack_price !== null) {
                    $shouldIncludeSnack = match($pmSnackPolicy) {
                        'required' => true, // Always include if required
                        'optional' => $includePmSnack, // Include if user selected
                        'hidden' => false // Never include if hidden
                    };
                    
                    $snackTotal = ($adults * $tier->adult_pm_snack_price) + ($children * $tier->child_pm_snack_price);
                    $pmSnack = new DayTourMealLineItemDTO(
                        adultPrice: (float) $tier->adult_pm_snack_price,
                        childPrice: (float) $tier->child_pm_snack_price,
                        adults: $adults,
                        children: $children,
                        total: $snackTotal,
                        applied: $shouldIncludeSnack
                    );
                }
            }
        }
        
        return new DayTourMealBreakdownDTO(
            lunch: $lunch,
            pmSnack: $pmSnack
        );
    }

    /**
     * Find an active meal program for a specific date
     * Programs are active within their scope (date range + months)
     * Weekly patterns only determine buffet vs free breakfast, not program activity
     */
    private function findActiveProgramForDate(Carbon $date): ?MealProgram
    {
        $activePrograms = $this->programRepository->getActive();
        foreach ($activePrograms as $program) {
            if ($this->isProgramActiveForDate($program, $date)) {
                return $program;
            }
        }
        
        return null;
    }

    /**
     * Check if a program is active for a specific date
     * This is based on your concept: programs are active within scope, 
     * weekly patterns only affect meal type (buffet vs free breakfast)
     */
    private function isProgramActiveForDate(MealProgram $program, Carbon $date): bool
    {
        switch ($program->scope_type) {
            case 'always':
                return true;
                
            case 'date_range':
                return $this->isInDateRange($program, $date);
                
            case 'months':
                return $this->isInMonths($program, $date);
                
            case 'weekly':
                // For weekly programs, the program is still active on all days
                // The weekly pattern determines meal type, not program activity
                return true;
                
            case 'composite':
                // For composite: check date range AND months (ignore weekly patterns for program activity)
                
                // Check date range if specified
                if ($program->date_start && $program->date_end && !$this->isInDateRange($program, $date)) {
                    return false;
                }
                // Check months if specified
                if ($program->months && !empty($program->months) && !$this->isInMonths($program, $date)) {
                    return false;
                }
                
                // Program is active within scope regardless of weekly pattern
                return true;
                
            default:
                return false;
        }
    }

    /**
     * Find a meal program that applies for breakfast pricing purposes
     * This checks date range and months but ignores weekly patterns
     */
    private function findProgramForBreakfastPricing(Carbon $date): ?MealProgram
    {
        $activePrograms = $this->programRepository->getActive();
        foreach ($activePrograms as $program) {
            // Check if date falls within program scope (ignoring weekly patterns for breakfast pricing)
            if ($this->isProgramInScopeForBreakfastPricing($program, $date)) {
                return $program;
            }
        }
        
        return null;
    }

    /**
     * Check if a program is in scope for breakfast pricing (ignores weekly patterns)
     */
    private function isProgramInScopeForBreakfastPricing(MealProgram $program, Carbon $date): bool
    {
        switch ($program->scope_type) {
            case 'always':
                return true;
                
            case 'date_range':
                return $this->isInDateRange($program, $date);
                
            case 'months':
                return $this->isInMonths($program, $date);
                
            case 'weekly':
                // For weekly programs, we still need to check the weekly pattern for breakfast pricing
                return $this->isInWeeklyPattern($program, $date);
                
            case 'composite':
                // For composite: check date range AND months (but ignore weekly patterns for breakfast pricing)
                
                // Check date range if specified
                if ($program->date_start && $program->date_end && !$this->isInDateRange($program, $date)) {
                    return false;
                }
                
                // Check months if specified
                if ($program->months && !empty($program->months) && !$this->isInMonths($program, $date)) {
                    return false;
                }
                
                // For breakfast pricing, we don't check weekly patterns in composite programs
                return true;
                
            default:
                return false;
        }
    }

    private function isInDateRange(MealProgram $program, Carbon $date): bool
    {
        if (!$program->date_start || !$program->date_end) {
            return false;
        }
        
        // Simple date-only comparison using Y-m-d format
        $dateString = $date->format('Y-m-d');
        $startDateString = $program->date_start->format('Y-m-d');
        $endDateString = $program->date_end->format('Y-m-d');
        
        return $dateString >= $startDateString && $dateString <= $endDateString;
    }

    private function isInMonths(MealProgram $program, Carbon $date): bool
    {
        if (!$program->months || empty($program->months)) {
            return false;
        }
        return in_array($date->month, $program->months);
    }

    private function isInWeeklyPattern(MealProgram $program, Carbon $date): bool
    {
        $dayOfWeek = strtoupper($date->format('D')); // MON, TUE, etc.
        
        // Check custom weekdays first
        if ($program->weekdays && !empty($program->weekdays)) {
            return in_array($dayOfWeek, $program->weekdays);
        }
        
        // Check weekend definition
        switch ($program->weekend_definition) {
            case 'SAT_SUN':
                return in_array($dayOfWeek, ['SAT', 'SUN']);
                
            case 'FRI_SUN':
                return in_array($dayOfWeek, ['FRI', 'SAT', 'SUN']);
                
            case 'CUSTOM':
                return true;
                
            default:
                return false;
        }
    }

    public function getLunchAndSnackPrices(Carbon $date): array
    {
        // Get property timezone
        $timezone = config('resort.timezone', 'Asia/Singapore');
        $localDate = $date->copy()->setTimezone($timezone)->startOfDay();
        
        // Get active meal program for this specific date
        $program = $this->calendarService->getActiveProgramForDate($localDate);
        
        if (!$program) {
            return ['lunch' => null, 'snack' => null];
        }
        
        // Check if buffet is active on this date
        $isBuffetActive = $this->calendarService->isBuffetActiveOn($localDate);
        
        $tier = $this->getPricingTierForDate($program->id, $localDate);
        
        if (!$tier) {
            return ['lunch' => null, 'snack' => null];
        }
        
        $lunchPrices = null;
        $snackPrices = null;
        
        // Get lunch prices ONLY if buffet is active
        if ($isBuffetActive && $tier->adult_lunch_price !== null && $tier->child_lunch_price !== null) {
            $lunchPrices = [
                'adult' => (float) $tier->adult_lunch_price,
                'child' => (float) $tier->child_lunch_price
            ];
        }
        
        // Get snack prices if available (INDEPENDENT of buffet status for Day Tours)
        // PM Snacks can be available even when buffet lunch is not
        if ($tier->adult_pm_snack_price !== null && $tier->child_pm_snack_price !== null) {
            $snackPrices = [
                'adult' => (float) $tier->adult_pm_snack_price,
                'child' => (float) $tier->child_pm_snack_price
            ];
        }
        
        return [
            'lunch' => $lunchPrices,
            'snack' => $snackPrices
        ];
    }
}
