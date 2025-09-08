<?php

namespace App\Services;

use App\Contracts\Repositories\MealCalendarOverrideRepositoryInterface;
use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\Models\MealProgram;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MealCalendarService implements MealCalendarServiceInterface
{
    public function __construct(
        private MealProgramRepositoryInterface $programRepository,
        private MealCalendarOverrideRepositoryInterface $overrideRepository
    ) {}

    public function isBuffetActiveOn(Carbon $date): bool
    {
        $program = $this->getActiveProgram();
        
        if (!$program) {
            return false;
        }

        return $this->isProgramActiveOnDate($program, $date);
    }

    public function getAvailabilityForDateRange(Carbon $startDate, Carbon $endDate): array
    {
        $program = $this->getActiveProgram();
        $availability = [];
        
        $current = $startDate->copy();
        while ($current->lt($endDate)) { // Changed from lte to lt - exclude check-out date
            $availability[$current->format('Y-m-d')] = $program && $this->isProgramActiveOnDate($program, $current) 
                ? 'buffet' 
                : 'free_breakfast';
            $current->addDay();
        }
        
        return $availability;
    }

    public function previewProgramCalendar(int $programId, Carbon $startDate, Carbon $endDate): array
    {
        $program = $this->programRepository->find($programId, ['calendarOverrides']);
        
        if (!$program) {
            return [];
        }
        
        $availability = [];
        $current = $startDate->copy();
        
        while ($current->lt($endDate)) { // Changed from lte to lt - exclude check-out date
            $availability[$current->format('Y-m-d')] = $this->isProgramActiveOnDate($program, $current) 
                ? 'buffet' 
                : 'free_breakfast';
            $current->addDay();
        }
        
        return $availability;
    }

    public function getActiveProgramForDate(Carbon $date): ?MealProgram
    {
        $activePrograms = $this->programRepository->getActive();
        
        foreach ($activePrograms as $program) {
            if ($this->programScopeMatches($program, $date)) {
                return $program;
            }
        }
        
        return null;
    }

    private function getActiveProgram(): ?MealProgram
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

    private function isProgramActiveOnDate(MealProgram $program, Carbon $date): bool
    {
        // 1. Check if program scope applies to this date first
        if (!$this->programScopeMatches($program, $date)) {
            return false;
        }

        // 2. Check for calendar overrides (highest precedence)
        $override = $this->getOverrideForDate($program->id, $date);
        if ($override) {
            return $override->is_active;
        }

        // 3. Check program's buffet enabled setting
        return $program->buffet_enabled ?? true; // Default to true for backward compatibility
    }

    private function programScopeMatches(MealProgram $program, Carbon $date): bool
    {
        // Check scope type
        switch ($program->scope_type) {
            case 'always':
                return true;
                
            case 'date_range':
                return $this->isInDateRange($program, $date);
                
            case 'months':
                return $this->isInMonths($program, $date);
                
            case 'weekly':
                return $this->isInWeeklyPattern($program, $date);
                
            case 'composite':
                // For composite, check all applicable rules
                if ($program->date_start && $program->date_end && !$this->isInDateRange($program, $date)) {
                    return false;
                }
                if ($program->months && !$this->isInMonths($program, $date)) {
                    return false;
                }
                if ($program->weekdays && !$this->isInWeeklyPattern($program, $date)) {
                    return false;
                }
                return true;
                
            default:
                return false;
        }
    }

    private function getOverrideForDate(int $programId, Carbon $date)
    {
        // First check for date-specific override
        $dateOverride = $this->overrideRepository->getByProgramAndDate($programId, $date);
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

    private function isInDateRange(MealProgram $program, Carbon $date): bool
    {
        if (!$program->date_start || !$program->date_end) {
            return false;
        }
        
        return $date->between($program->date_start, $program->date_end);
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
                // If CUSTOM but no weekdays defined, default to false
                return false;
                
            default:
                return false;
        }
    }
}
