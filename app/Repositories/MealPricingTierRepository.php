<?php

namespace App\Repositories;

use App\Contracts\Repositories\MealPricingTierRepositoryInterface;
use App\Models\MealPricingTier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class MealPricingTierRepository implements MealPricingTierRepositoryInterface
{
    public function find(int $id): ?MealPricingTier
    {
        return MealPricingTier::find($id);
    }

    public function getByProgramId(int $programId): Collection
    {
        return MealPricingTier::where('meal_program_id', $programId)
            ->orderBy('effective_from', 'desc')
            ->get();
    }

    public function getEffectiveTierForDate(int $programId, Carbon $date): ?MealPricingTier
    {
        // First try to find a tier that explicitly covers the date
        $tier = MealPricingTier::where('meal_program_id', $programId)
            ->where(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    $q->whereDate('effective_from', '<=', $date)
                      ->where(function ($q2) use ($date) {
                          $q2->whereDate('effective_to', '>=', $date)
                             ->orWhereNull('effective_to');
                      });
                });
            })
            ->orderBy('effective_from', 'desc')
            ->first();

        // If no tier explicitly covers the date, get the latest tier on or before the date
        if (!$tier) {
            $tier = MealPricingTier::where('meal_program_id', $programId)
                ->where(function ($query) use ($date) {
                    $query->whereDate('effective_from', '<=', $date)
                          ->orWhereNull('effective_from');
                })
                ->orderBy('effective_from', 'desc')
                ->first();
        }

        return $tier;
    }

    public function create(array $data): MealPricingTier
    {
        return MealPricingTier::create($data);
    }

    public function update(MealPricingTier $tier, array $data): MealPricingTier
    {
        $tier->update($data);
        return $tier->fresh();
    }

    public function delete(MealPricingTier $tier): bool
    {
        return $tier->delete();
    }
}
