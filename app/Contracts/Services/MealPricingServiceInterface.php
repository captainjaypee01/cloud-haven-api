<?php

namespace App\Contracts\Services;

use App\DTO\MealQuoteDTO;
use Carbon\Carbon;

interface MealPricingServiceInterface
{
    /**
     * Quote meal prices for a stay
     *
     * @param Carbon $checkIn
     * @param Carbon $checkOut
     * @param int $adults
     * @param int $children
     * @return MealQuoteDTO
     */
    public function quoteForStay(Carbon $checkIn, Carbon $checkOut, int $adults, int $children): MealQuoteDTO;

    /**
     * Get the active meal program (latest updated_at if multiple)
     *
     * @return \App\Models\MealProgram|null
     */
    public function getActiveMealProgram(): ?\App\Models\MealProgram;

    /**
     * Get pricing tier for a specific date
     *
     * @param int $programId
     * @param Carbon $date
     * @return \App\Models\MealPricingTier|null
     */
    public function getPricingTierForDate(int $programId, Carbon $date): ?\App\Models\MealPricingTier;
}
