<?php

namespace App\Services;

use App\Contracts\Repositories\MealPricingTierRepositoryInterface;
use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\Contracts\Repositories\MealCalendarOverrideRepositoryInterface;
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
        private MealPricingTierRepositoryInterface $pricingTierRepository,
        private MealCalendarOverrideRepositoryInterface $overrideRepository
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
        
        // Iterate stay nights (check-in to check-out)
        // For a stay Oct 3-6: 3 nights (Oct 3-4, Oct 4-5, Oct 5-6)
        // Each night corresponds to breakfast the next day
        $current = $checkIn->copy()->setTimezone($timezone)->startOfDay();
        $end = $checkOut->copy()->setTimezone($timezone)->startOfDay()->subDay();
        while ($current->lte($end)) {
            // Breakfast date is the next day after the stay night
            $breakfastDate = $current->copy()->addDay();
            
            // Find the active meal program for this stay night FIRST
            $program = $this->findActiveProgramForDate($current);
            
            if ($program) {
                \Log::info('Active program found', ['program_id' => $program->id, 'program_name' => $program->name, 'date' => $current->format('Y-m-d')]);
                
                // Get pricing tier for this stay night
                $tier = $this->getPricingTierForDate($program->id, $current);
                
                // NOW check if buffet is active for this specific program and date
                // Use a more direct approach - check overrides for this specific program
                $isBuffetActive = $this->isBuffetActiveForProgramAndDate($program, $current);
                if ($tier) {
                    $nights[] = new MealNightDTO(
                        date: $current->copy(), // stay night date
                        type: $isBuffetActive ? 'buffet' : 'free_breakfast',
                        startDate: $isBuffetActive ? $current->copy() : $current->copy(), // buffet: stay night, free breakfast: stay night
                        endDate: $isBuffetActive ? $breakfastDate->copy() : $breakfastDate->copy(), // buffet: breakfast day, free breakfast: breakfast day
                        adultPrice: $isBuffetActive ? (float) $tier->adult_price : null,
                        childPrice: $isBuffetActive ? (float) $tier->child_price : null,
                        adults: 0, // No calculations, just program info
                        children: 0,
                        nightTotal: 0.0,
                        adultBreakfastPrice: (float) ($tier->adult_breakfast_price ?? 0),
                        childBreakfastPrice: (float) ($tier->child_breakfast_price ?? 0),
                        extraGuestFee: (float) ($tier->extra_guest_fee ?? 0),
                        extraAdults: 0,
                        extraChildren: 0,
                        breakfastTotal: 0.0,
                        extraGuestFeeTotal: 0.0
                    );
                } else {
                        // No pricing tier found, but program exists
                    $nights[] = new MealNightDTO(
                        date: $current->copy(), // stay night date
                        type: 'free_breakfast',
                        startDate: $current->copy(), // stay night (when they check in and start consuming)
                        endDate: $breakfastDate->copy(), // breakfast day (when they finish breakfast)
                        adults: 0,
                        children: 0,
                        nightTotal: 0.0,
                        extraGuestFeeTotal: 0.0
                    );
                }
            } else {
                    // No active program for this date
                $nights[] = new MealNightDTO(
                    date: $current->copy(), // stay night date
                    type: 'free_breakfast',
                    startDate: $current->copy(), // stay night (when they check in and start consuming)
                    endDate: $breakfastDate->copy(), // breakfast day (when they finish breakfast)
                    adults: 0,
                    children: 0,
                    nightTotal: 0.0,
                    extraGuestFeeTotal: 0.0
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
        
        // Iterate stay nights (check-in to check-out)
        // For a stay Oct 3-6: 3 nights (Oct 3-4, Oct 4-5, Oct 5-6)
        // Each night corresponds to breakfast the next day
        $current = $checkIn->copy()->setTimezone($timezone)->startOfDay();
        $end = $checkOut->copy()->setTimezone($timezone)->startOfDay()->subDay();
        
        while ($current->lte($end)) {
            // Breakfast date is the next day after the stay night
            $breakfastDate = $current->copy()->addDay();
            
            // Check if buffet is active on the stay night (for buffet dinner)
            $isBuffetActive = $this->calendarService->isBuffetActiveOn($current);
            $program = $this->calendarService->getActiveProgramForDate($current);
            
            if ($isBuffetActive && $program) {
                // Get pricing tier for this stay night (buffet dinner date)
                $tier = $this->getPricingTierForDate($program->id, $current);
                
                if ($tier) {
                    // Calculate buffet meal total for all guests (including extra guests)
                    $totalGuests = $adults + $children;
                    $nightTotal = ($adults * $tier->adult_price) + ($children * $tier->child_price);
                    
                    // Calculate extra guest fees (entrance fees, extra mattresses, etc.) if there are extra guests
                    $extraGuestFeeTotal = 0.0;
                    $extraAdults = 0;
                    $extraChildren = 0;
                    
                    if ($rooms && $tier->extra_guest_fee !== null) {
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
                        
                        if ($totalExtraGuests > 0) {
                            // Use single extra guest fee for all extra guests
                            $extraGuestFee = (float) $tier->extra_guest_fee;
                            $extraGuestFeeTotal = $totalExtraGuests * $extraGuestFee;
                            
                            // For display purposes, show all extras as "extra adults"
                            $extraAdults = $totalExtraGuests;
                            $extraChildren = 0;
                        }
                    }
                    
                    $nights[] = new MealNightDTO(
                        date: $current->copy(), // stay night date
                        type: 'buffet',
                        startDate: $current->copy(), // buffet dinner day (stay night)
                        endDate: $breakfastDate->copy(), // buffet breakfast day (next day)
                        adultPrice: (float) $tier->adult_price,
                        childPrice: (float) $tier->child_price,
                        adults: $adults,
                        children: $children,
                        nightTotal: $nightTotal,
                        extraGuestFee: $tier->extra_guest_fee ? (float) $tier->extra_guest_fee : null,
                        extraAdults: $extraAdults,
                        extraChildren: $extraChildren,
                        extraGuestFeeTotal: $extraGuestFeeTotal
                    );
                    
                    $mealSubtotal += $nightTotal + $extraGuestFeeTotal;
                } else {
                    // No pricing tier found, default to free breakfast
                    $nights[] = new MealNightDTO(
                        date: $current->copy(), // stay night date
                        type: 'free_breakfast',
                        startDate: $current->copy(), // stay night (when they check in and start consuming)
                        endDate: $breakfastDate->copy(), // breakfast day (when they finish breakfast)
                        adults: $adults,
                        children: $children,
                        nightTotal: 0.0,
                        extraGuestFeeTotal: 0.0
                    );
                }
            } else {
                // Buffet not active - calculate breakfast costs for extra guests
                $breakfastTotal = 0.0;
                $extraAdults = 0;
                $extraChildren = 0;
                $adultBreakfastPrice = null;
                $childBreakfastPrice = null;

                // For breakfast pricing, look for any program that covers this stay night
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
                    date: $current->copy(), // stay night date
                    type: 'free_breakfast',
                    startDate: $current->copy(), // stay night (when they check in and start consuming)
                    endDate: $breakfastDate->copy(), // breakfast day (when they finish breakfast)
                    adults: $adults,
                    children: $children,
                    nightTotal: $breakfastTotal,
                    adultBreakfastPrice: $adultBreakfastPrice,
                    childBreakfastPrice: $childBreakfastPrice,
                    extraAdults: $extraAdults,
                    extraChildren: $extraChildren,
                    breakfastTotal: $breakfastTotal,
                    extraGuestFeeTotal: 0.0
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
        // Parse the date without timezone conversion since meal programs are date-based, not time-based
        $localDate = $date->copy()->startOfDay();
        
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
            // \Log::info('program', ['program' => $program->id, 'date' => $date->format('Y-m-d')]);
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
        // Parse the date without timezone conversion since meal programs are date-based, not time-based
        $localDate = $date->copy()->startOfDay();
        
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

    /**
     * Check if buffet is active for a specific program and date
     * This bypasses the program lookup in isBuffetActiveOn() since we already have the program
     */
    private function isBuffetActiveForProgramAndDate(MealProgram $program, Carbon $date): bool
    {
        \Log::info('Checking buffet active for specific program', [
            'program_id' => $program->id,
            'program_name' => $program->name,
            'date' => $date->format('Y-m-d')
        ]);

        // 1. Check for calendar overrides first (highest precedence)
        // Access override repository directly since getOverrideForDate is private
        $override = $this->checkOverrideForProgramAndDate($program->id, $date);
        if ($override) {
            \Log::info('Override found for program', [
                'program_id' => $program->id,
                'override_id' => $override->id,
                'is_active' => $override->is_active,
                'date' => $date->format('Y-m-d')
            ]);
            return $override->is_active;
        }

        // 2. Check if program scope applies to this date (date range + months)
        if (!$this->isProgramActiveForDate($program, $date)) {
            \Log::info('Program not active for date', [
                'program_id' => $program->id,
                'date' => $date->format('Y-m-d')
            ]);
            return false;
        }

        // 3. Check if buffet is enabled for this program (default to true for backward compatibility)
        if ($program->buffet_enabled === false) {
            \Log::info('Program buffet disabled', [
                'program_id' => $program->id,
                'date' => $date->format('Y-m-d')
            ]);
            return false;
        }

        // 4. For composite programs, check weekly pattern to determine if buffet is active
        if ($program->scope_type === 'composite') {
            $weeklyActive = $this->isInWeeklyPattern($program, $date);
            \Log::info('Composite program weekly pattern check', [
                'program_id' => $program->id,
                'date' => $date->format('Y-m-d'),
                'weekly_active' => $weeklyActive
            ]);
            return $weeklyActive;
        }

        // For non-composite programs, buffet is active if the program is active
        \Log::info('Non-composite program - buffet is active', [
            'program_id' => $program->id,
            'date' => $date->format('Y-m-d')
        ]);
        return true;
    }

    /**
     * Check for calendar override for a specific program and date
     * Replicates the logic from MealCalendarService::getOverrideForDate
     */
    private function checkOverrideForProgramAndDate(int $programId, Carbon $date)
    {
        \Log::info('Checking override directly', [
            'program_id' => $programId,
            'date' => $date->format('Y-m-d')
        ]);

        // First check for date-specific override
        $dateOverride = $this->overrideRepository->getByProgramAndDate($programId, $date);
        
        \Log::info('Direct override check result', [
            'program_id' => $programId,
            'date' => $date->format('Y-m-d'),
            'override_found' => $dateOverride ? 'YES' : 'NO',
            'override_id' => $dateOverride?->id,
            'is_active' => $dateOverride?->is_active
        ]);
        
        if ($dateOverride) {
            return $dateOverride;
        }

        // Then check for month-wide override
        $monthOverride = $this->overrideRepository->getByProgramAndMonth($programId, $date->month, $date->year);
        if ($monthOverride) {
            return $monthOverride;
        }

        return null;
    }
}
