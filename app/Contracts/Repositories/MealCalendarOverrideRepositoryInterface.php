<?php

namespace App\Contracts\Repositories;

use App\Models\MealCalendarOverride;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface MealCalendarOverrideRepositoryInterface
{
    /**
     * Find override by ID
     *
     * @param int $id
     * @return MealCalendarOverride|null
     */
    public function find(int $id): ?MealCalendarOverride;

    /**
     * Get overrides for a meal program
     *
     * @param int $programId
     * @return Collection
     */
    public function getByProgramId(int $programId): Collection;

    /**
     * Get override for a specific date
     *
     * @param int $programId
     * @param Carbon $date
     * @return MealCalendarOverride|null
     */
    public function getByProgramAndDate(int $programId, Carbon $date): ?MealCalendarOverride;

    /**
     * Get month-wide override for a specific month
     *
     * @param int $programId
     * @param int $month
     * @param int $year
     * @return MealCalendarOverride|null
     */
    public function getByProgramAndMonth(int $programId, int $month, int $year): ?MealCalendarOverride;

    /**
     * Get overrides for date range
     *
     * @param int $programId
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Collection
     */
    public function getByProgramAndDateRange(int $programId, Carbon $startDate, Carbon $endDate): Collection;

    /**
     * Create a new override
     *
     * @param array $data
     * @return MealCalendarOverride
     */
    public function create(array $data): MealCalendarOverride;

    /**
     * Update an override
     *
     * @param MealCalendarOverride $override
     * @param array $data
     * @return MealCalendarOverride
     */
    public function update(MealCalendarOverride $override, array $data): MealCalendarOverride;

    /**
     * Delete an override
     *
     * @param MealCalendarOverride $override
     * @return bool
     */
    public function delete(MealCalendarOverride $override): bool;
}
