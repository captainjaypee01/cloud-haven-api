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

    public function quoteForStay(Carbon $checkIn, Carbon $checkOut, int $adults, int $children): MealQuoteDTO
    {
        $nights = [];
        $mealSubtotal = 0.0;
        $program = $this->getActiveMealProgram();
        
        // Get property timezone from config
        $timezone = config('resort.timezone', 'Asia/Singapore');
        
        // Iterate nights (check-in inclusive, check-out exclusive)
        $current = $checkIn->copy()->setTimezone($timezone)->startOfDay();
        $end = $checkOut->copy()->setTimezone($timezone)->startOfDay();
        
        while ($current->lt($end)) {
            $isBuffetActive = $this->calendarService->isBuffetActiveOn($current);
            
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
                // Buffet not active or no program
                $nights[] = new MealNightDTO(
                    date: $current->copy(),
                    type: 'free_breakfast',
                    adults: $adults,
                    children: $children,
                    nightTotal: 0.0
                );
            }
            
            $current->addDay();
        }
        
        $labels = [
            'inactive' => $program ? $program->inactive_label : 'Complimentary Breakfast Only'
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
        $program = $this->getActiveMealProgram();
        
        // Get property timezone
        $timezone = config('resort.timezone', 'Asia/Singapore');
        $localDate = $date->copy()->setTimezone($timezone)->startOfDay();
        
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

    public function getLunchAndSnackPrices(Carbon $date): array
    {
        $program = $this->getActiveMealProgram();
        
        if (!$program) {
            return ['lunch' => null, 'snack' => null];
        }
        
        // Get property timezone
        $timezone = config('resort.timezone', 'Asia/Singapore');
        $localDate = $date->copy()->setTimezone($timezone)->startOfDay();
        
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
