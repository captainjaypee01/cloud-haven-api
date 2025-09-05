<?php

namespace App\Contracts\Repositories;

use App\Models\MealPricingTier;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface MealPricingTierRepositoryInterface
{
    /**
     * Find pricing tier by ID
     *
     * @param int $id
     * @return MealPricingTier|null
     */
    public function find(int $id): ?MealPricingTier;

    /**
     * Get pricing tiers for a meal program
     *
     * @param int $programId
     * @return Collection
     */
    public function getByProgramId(int $programId): Collection;

    /**
     * Get effective pricing tier for a date
     *
     * @param int $programId
     * @param Carbon $date
     * @return MealPricingTier|null
     */
    public function getEffectiveTierForDate(int $programId, Carbon $date): ?MealPricingTier;

    /**
     * Create a new pricing tier
     *
     * @param array $data
     * @return MealPricingTier
     */
    public function create(array $data): MealPricingTier;

    /**
     * Update a pricing tier
     *
     * @param MealPricingTier $tier
     * @param array $data
     * @return MealPricingTier
     */
    public function update(MealPricingTier $tier, array $data): MealPricingTier;

    /**
     * Delete a pricing tier
     *
     * @param MealPricingTier $tier
     * @return bool
     */
    public function delete(MealPricingTier $tier): bool;
}
