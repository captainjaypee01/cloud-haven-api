<?php

namespace App\Contracts\Services;

use Carbon\Carbon;

interface MealCalendarServiceInterface
{
    /**
     * Determine if buffet is active on a specific date
     * Precedence: overrides > date range > months > weekly/weekend > always
     *
     * @param Carbon $date
     * @return bool
     */
    public function isBuffetActiveOn(Carbon $date): bool;

    /**
     * Get meal availability for a date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array<string, string> Date => 'buffet' or 'free_breakfast'
     */
    public function getAvailabilityForDateRange(Carbon $startDate, Carbon $endDate): array;

    /**
     * Preview calendar for a specific program
     *
     * @param int $programId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array<string, string> Date => 'buffet' or 'free_breakfast'
     */
    public function previewProgramCalendar(int $programId, Carbon $startDate, Carbon $endDate): array;
}
