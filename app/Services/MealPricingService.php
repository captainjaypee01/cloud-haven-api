<?php

namespace App\Services;

use App\Contracts\Repositories\MealPricingTierRepositoryInterface;
use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\Contracts\Services\MealPricingServiceInterface;
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
            'inactive' => $program ? $program->inactive_label : 'Free Breakfast'
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
}
