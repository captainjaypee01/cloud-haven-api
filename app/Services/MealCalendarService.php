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
        Log::info('isBuffetActiveOn called', [
            'date' => $date->format('Y-m-d'),
            'timestamp' => $date->toISOString()
        ]);

        $program = $this->getActiveProgramForDate($date);
        
        if (!$program) {
            Log::info('No active program found for date', ['date' => $date->format('Y-m-d')]);
            return false;
        }

        Log::info('Active program found', [
            'program_id' => $program->id,
            'program_name' => $program->name,
            'date' => $date->format('Y-m-d')
        ]);

        // 1. Check for calendar overrides first (highest precedence)
        $override = $this->getOverrideForDate($program->id, $date);
        if ($override) {
            return $override->is_active;
        }

        // 2. Check if program scope applies to this date (date range + months)
        if (!$this->programScopeMatches($program, $date)) {
            return false;
        }

        // 3. Check if buffet is enabled for this program (default to true for backward compatibility)
        if ($program->buffet_enabled === false) {
            return false;
        }

        // 4. For composite programs, check weekly pattern to determine if buffet is active
        // For other scope types (date_range, months, weekly, always), buffet is active if program is active
        if ($program->scope_type === 'composite') {
            return $this->isInWeeklyPattern($program, $date);
        }

        // For non-composite programs, buffet is active if the program is active
        return true;
    }

    public function getAvailabilityForDateRange(Carbon $startDate, Carbon $endDate): array
    {
        $program = $this->getActiveProgram();
        $availability = [];
        
        $current = $startDate->copy();
        while ($current->lte($endDate)) { // Include the end date for calendar preview
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
        
        while ($current->lte($endDate)) { // Include the end date for calendar preview
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

    public function getAvailableDateRanges(): array
    {
        $activePrograms = $this->programRepository->getActive();
        
        if ($activePrograms->isEmpty()) {
            return [];
        }
        
        $ranges = [];
        $today = Carbon::today();
        
        foreach ($activePrograms as $program) {
            $programRanges = $this->getProgramDateRanges($program, $today);
            $ranges = array_merge($ranges, $programRanges);
        }
        
        // Merge overlapping ranges and sort
        return $this->mergeDateRanges($ranges);
    }

    private function getProgramDateRanges(MealProgram $program, Carbon $today): array
    {
        $ranges = [];
        
        switch ($program->scope_type) {
            case 'always':
                // Always active - return a range from today to 1 year from now
                $ranges[] = [
                    'start' => $today->format('Y-m-d'),
                    'end' => $today->copy()->addYear()->format('Y-m-d')
                ];
                break;
                
            case 'date_range':
                if ($program->date_start && $program->date_end) {
                    $start = max($program->date_start, $today);
                    if ($start->lte($program->date_end)) {
                        $ranges[] = [
                            'start' => $start->format('Y-m-d'),
                            'end' => $program->date_end->format('Y-m-d')
                        ];
                    }
                }
                break;
                
            case 'months':
                if ($program->months && !empty($program->months)) {
                    // For month-based programs, create ranges for the next 2 years
                    $currentYear = $today->year;
                    for ($year = $currentYear; $year <= $currentYear + 1; $year++) {
                        foreach ($program->months as $month) {
                            $monthStart = Carbon::create($year, $month, 1);
                            $monthEnd = $monthStart->copy()->endOfMonth();
                            
                            // Only include if the month hasn't passed yet
                            if ($monthEnd->gte($today)) {
                                $start = max($monthStart, $today);
                                $ranges[] = [
                                    'start' => $start->format('Y-m-d'),
                                    'end' => $monthEnd->format('Y-m-d')
                                ];
                            }
                        }
                    }
                }
                break;
                
            case 'weekly':
                // For weekly programs, check each day within a reasonable range
                $ranges = array_merge($ranges, $this->calculateWeeklyDateRanges($program, $today));
                break;
                
            case 'composite':
                // For composite programs, calculate based on the combined rules
                // But for booking availability, ignore weekend definitions - only use date range and months
                $ranges = array_merge($ranges, $this->calculateCompositeForBooking($program, $today));
                break;
        }
        
        return $ranges;
    }

    private function mergeDateRanges(array $ranges): array
    {
        if (empty($ranges)) {
            return [];
        }
        
        // Sort ranges by start date
        usort($ranges, function($a, $b) {
            return strcmp($a['start'], $b['start']);
        });
        
        $merged = [$ranges[0]];
        
        for ($i = 1; $i < count($ranges); $i++) {
            $current = $ranges[$i];
            $last = &$merged[count($merged) - 1];
            
            // If current range overlaps or is adjacent to the last range, merge them
            if ($current['start'] <= $last['end'] || 
                Carbon::parse($current['start'])->subDay()->format('Y-m-d') === $last['end']) {
                $last['end'] = max($last['end'], $current['end']);
            } else {
                $merged[] = $current;
            }
        }
        
        return $merged;
    }

    private function calculateWeeklyDateRanges(MealProgram $program, Carbon $today): array
    {
        $ranges = [];
        $endDate = $today->copy()->addYear(); // Check up to 1 year ahead
        $current = $today->copy();
        
        $rangeStart = null;
        $rangeEnd = null;
        
        while ($current->lte($endDate)) {
            if ($this->isInWeeklyPattern($program, $current)) {
                if ($rangeStart === null) {
                    $rangeStart = $current->copy();
                }
                $rangeEnd = $current->copy();
            } else {
                if ($rangeStart !== null && $rangeEnd !== null) {
                    $ranges[] = [
                        'start' => $rangeStart->format('Y-m-d'),
                        'end' => $rangeEnd->format('Y-m-d')
                    ];
                    $rangeStart = null;
                    $rangeEnd = null;
                }
            }
            $current->addDay();
        }
        
        // Don't forget the last range if it extends to the end
        if ($rangeStart !== null) {
            $ranges[] = [
                'start' => $rangeStart->format('Y-m-d'),
                'end' => $rangeEnd->format('Y-m-d')
            ];
        }
        
        return $ranges;
    }

    private function calculateCompositeDateRanges(MealProgram $program, Carbon $today): array
    {
        $ranges = [];
        
        // For composite programs, we need to respect all the constraints
        // Start with the base date range or a reasonable default
        $startDate = $today->copy();
        $endDate = $today->copy()->addYear();
        
        // If there's a specific date range, use that
        if ($program->date_start && $program->date_end) {
            $startDate = max($program->date_start, $today);
            $endDate = $program->date_end;
            
            // If the date range has already passed, return empty
            if ($startDate->gt($endDate)) {
                return [];
            }
        }
        
        // If there are specific months AND date range, we need to handle the intersection properly
        // For your October Weekends program: date range Oct 1 - Nov 9, month restriction October
        // This should include dates that are within the date range AND meet weekly patterns
        // even if they fall outside the month restriction (in this case, Nov 1-9)
        if ($program->months && !empty($program->months)) {
            // If we have both date range and month restrictions, the date range takes precedence
            // but we still apply monthly restrictions within reasonable bounds
            if ($program->date_start && $program->date_end) {
                // Use the date range, but prioritize it over month restrictions
                return $this->calculateCompositeWithDateRange($program, $startDate, $endDate);
            } else {
                // Only month restrictions, no date range
                return $this->calculateCompositeWithMonths($program, $startDate, $endDate);
            }
        }
        
        // If no month restrictions, check the date range with weekly patterns
        return $this->calculateCompositeWithDateRange($program, $startDate, $endDate);
    }

    private function calculateCompositeWithMonths(MealProgram $program, Carbon $startDate, Carbon $endDate): array
    {
        $ranges = [];
        $currentYear = $startDate->year;
        $endYear = $endDate->year;
        
        for ($year = $currentYear; $year <= $endYear; $year++) {
            foreach ($program->months as $month) {
                $monthStart = Carbon::create($year, $month, 1);
                $monthEnd = $monthStart->copy()->endOfMonth();
                
                // Intersect with the overall date range
                $rangeStart = max($monthStart, $startDate);
                $rangeEnd = min($monthEnd, $endDate);
                
                // Only include if the range is valid and hasn't passed
                if ($rangeStart->lte($rangeEnd) && $rangeEnd->gte($startDate)) {
                    // If there are weekly patterns, apply them
                    if ($program->weekdays || $program->weekend_definition) {
                        $weeklyRanges = $this->calculateCompositeWithDateRange($program, $rangeStart, $rangeEnd);
                        $ranges = array_merge($ranges, $weeklyRanges);
                    } else {
                        $ranges[] = [
                            'start' => $rangeStart->format('Y-m-d'),
                            'end' => $rangeEnd->format('Y-m-d')
                        ];
                    }
                }
            }
        }
        
        return $ranges;
    }

    private function calculateCompositeWithDateRange(MealProgram $program, Carbon $startDate, Carbon $endDate): array
    {
        $ranges = [];
        $current = $startDate->copy();
        
        $rangeStart = null;
        $rangeEnd = null;
        
        while ($current->lte($endDate)) {
            if ($this->isInWeeklyPattern($program, $current)) {
                if ($rangeStart === null) {
                    $rangeStart = $current->copy();
                }
                $rangeEnd = $current->copy();
            } else {
                if ($rangeStart !== null && $rangeEnd !== null) {
                    $ranges[] = [
                        'start' => $rangeStart->format('Y-m-d'),
                        'end' => $rangeEnd->format('Y-m-d')
                    ];
                    $rangeStart = null;
                    $rangeEnd = null;
                }
            }
            $current->addDay();
        }
        
        // Don't forget the last range if it extends to the end
        if ($rangeStart !== null) {
            $ranges[] = [
                'start' => $rangeStart->format('Y-m-d'),
                'end' => $rangeEnd->format('Y-m-d')
            ];
        }
        
        return $ranges;
    }

    private function calculateCompositeForBooking(MealProgram $program, Carbon $today): array
    {
        $ranges = [];
        
        // For booking availability, only consider date range and months, NOT weekend definitions
        
        // If there's a specific date range, use that as the primary constraint
        if ($program->date_start && $program->date_end) {
            $startDate = max($program->date_start, $today);
            $endDate = $program->date_end;
            
            // If the date range has already passed, return empty
            if ($startDate->gt($endDate)) {
                return [];
            }
            
            // If there are specific months, we need to decide:
            // Should the date range override months, or should months restrict the date range?
            // Based on user feedback, date range should take precedence for booking availability
            // So we use the full date range regardless of month restrictions
            return [[
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]];
        }
        
        // If no date range but has months, create ranges for those months
        if ($program->months && !empty($program->months)) {
            $currentYear = $today->year;
            
            // Check at least 2 years ahead to handle recurring yearly programs
            $endYear = $currentYear + 1;
            
            for ($year = $currentYear; $year <= $endYear; $year++) {
                foreach ($program->months as $month) {
                    $monthStart = Carbon::create($year, $month, 1);
                    $monthEnd = $monthStart->copy()->endOfMonth();
                    
                    // Only include if the month hasn't passed yet
                    if ($monthEnd->gte($today)) {
                        $start = max($monthStart, $today);
                        $ranges[] = [
                            'start' => $start->format('Y-m-d'),
                            'end' => $monthEnd->format('Y-m-d')
                        ];
                    }
                }
            }
        }
        
        return $ranges;
    }

    private function intersectDateRangeWithMonths(Carbon $startDate, Carbon $endDate, array $months): array
    {
        $ranges = [];
        $currentYear = $startDate->year;
        $endYear = $endDate->year;
        
        for ($year = $currentYear; $year <= $endYear; $year++) {
            foreach ($months as $month) {
                $monthStart = Carbon::create($year, $month, 1);
                $monthEnd = $monthStart->copy()->endOfMonth();
                
                // Find the intersection of the date range and this month
                $intersectionStart = max($monthStart, $startDate);
                $intersectionEnd = min($monthEnd, $endDate);
                
                // Only include if there's a valid intersection
                if ($intersectionStart->lte($intersectionEnd)) {
                    $ranges[] = [
                        'start' => $intersectionStart->format('Y-m-d'),
                        'end' => $intersectionEnd->format('Y-m-d')
                    ];
                }
            }
        }
        
        return $ranges;
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
        // 1. Check for calendar overrides first (highest precedence)
        $override = $this->getOverrideForDate($program->id, $date);
        if ($override) {
            return $override->is_active;
        }

        // 2. Check if program scope applies to this date
        if (!$this->programScopeMatches($program, $date)) {
            return false;
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
                // For composite, check date range and months but NOT weekly patterns
                // Weekly patterns only determine buffet availability, not program activity
                
                // Check date range if specified
                if ($program->date_start && $program->date_end && !$this->isInDateRange($program, $date)) {
                    return false;
                }
                
                // Check months if specified (always apply if present)
                if ($program->months && !empty($program->months) && !$this->isInMonths($program, $date)) {
                    return false;
                }
                
                // Program is active within scope regardless of weekly pattern
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
                // If CUSTOM but no weekdays defined, treat as "all days" for composite programs
                // This allows composite programs to work with date ranges and months without day restrictions
                return true;
                
            default:
                return false;
        }
    }
}
