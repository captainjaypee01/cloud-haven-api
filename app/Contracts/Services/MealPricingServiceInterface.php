<?php

namespace App\Contracts\Services;

use App\DTO\DayTour\DayTourMealBreakdownDTO;
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
     * @param array|null $rooms Room information for breakfast cost calculation
     * @return MealQuoteDTO
     */
    public function quoteForStay(Carbon $checkIn, Carbon $checkOut, int $adults, int $children, ?array $rooms = null): MealQuoteDTO;

    /**
     * Get meal program information for a stay (simplified approach)
     * Returns only meal program info and pricing, no calculations
     *
     * @param Carbon $checkIn
     * @param Carbon $checkOut
     * @return MealQuoteDTO
     */
    public function getMealProgramInfoForStay(Carbon $checkIn, Carbon $checkOut): MealQuoteDTO;

    /**
     * Quote meal prices for Day Tour
     *
     * @param Carbon $date
     * @param int $adults
     * @param int $children
     * @param bool $includeLunch
     * @param bool $includePmSnack
     * @param string $pmSnackPolicy (hidden|optional|required)
     * @return DayTourMealBreakdownDTO
     */
    public function quoteDayTourMeals(
        Carbon $date,
        int $adults,
        int $children,
        bool $includeLunch,
        bool $includePmSnack,
        string $pmSnackPolicy
    ): DayTourMealBreakdownDTO;

    /**
     * Get lunch and snack prices for a date
     *
     * @param Carbon $date
     * @return array{lunch: array{adult: float, child: float}|null, snack: array{adult: float, child: float}|null}
     */
    public function getLunchAndSnackPrices(Carbon $date): array;

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
