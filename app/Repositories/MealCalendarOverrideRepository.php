<?php

namespace App\Repositories;

use App\Contracts\Repositories\MealCalendarOverrideRepositoryInterface;
use App\Models\MealCalendarOverride;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class MealCalendarOverrideRepository implements MealCalendarOverrideRepositoryInterface
{
    public function find(int $id): ?MealCalendarOverride
    {
        return MealCalendarOverride::find($id);
    }

    public function getByProgramId(int $programId): Collection
    {
        return MealCalendarOverride::where('meal_program_id', $programId)
            ->orderBy('date', 'asc')
            ->get();
    }

    public function getByProgramAndDate(int $programId, Carbon $date): ?MealCalendarOverride
    {
        return MealCalendarOverride::where('meal_program_id', $programId)
            ->whereDate('date', $date)
            ->first();
    }

    public function getByProgramAndDateRange(int $programId, Carbon $startDate, Carbon $endDate): Collection
    {
        return MealCalendarOverride::where('meal_program_id', $programId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('date', 'asc')
            ->get();
    }

    public function create(array $data): MealCalendarOverride
    {
        return MealCalendarOverride::create($data);
    }

    public function update(MealCalendarOverride $override, array $data): MealCalendarOverride
    {
        $override->update($data);
        return $override->fresh();
    }

    public function delete(MealCalendarOverride $override): bool
    {
        return $override->delete();
    }
}
