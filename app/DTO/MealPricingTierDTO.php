<?php

namespace App\DTO;

use App\Models\MealPricingTier;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class MealPricingTierDTO extends Data
{
    public function __construct(
        public ?int $id,
        #[MapInputName('meal_program_id')]
        public int $mealProgramId,
        public string $currency,
        #[MapInputName('adult_price')]
        public float $adultPrice,
        #[MapInputName('child_price')]
        public float $childPrice,
        #[MapInputName('adult_lunch_price')]
        public ?float $adultLunchPrice,
        #[MapInputName('child_lunch_price')]
        public ?float $childLunchPrice,
        #[MapInputName('adult_pm_snack_price')]
        public ?float $adultPmSnackPrice,
        #[MapInputName('child_pm_snack_price')]
        public ?float $childPmSnackPrice,
        #[MapInputName('adult_dinner_price')]
        public ?float $adultDinnerPrice,
        #[MapInputName('child_dinner_price')]
        public ?float $childDinnerPrice,
        #[MapInputName('adult_breakfast_price')]
        public ?float $adultBreakfastPrice,
        #[MapInputName('child_breakfast_price')]
        public ?float $childBreakfastPrice,
        #[MapInputName('effective_from')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d', type: Carbon::class)]
        public ?Carbon $effectiveFrom,
        #[MapInputName('effective_to')]
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d', type: Carbon::class)]
        public ?Carbon $effectiveTo
    ) {}

    public static function fromModel(MealPricingTier $tier): self
    {
        return new self(
            id: $tier->id,
            mealProgramId: $tier->meal_program_id,
            currency: $tier->currency,
            adultPrice: (float) $tier->adult_price,
            childPrice: (float) $tier->child_price,
            adultLunchPrice: $tier->adult_lunch_price ? (float) $tier->adult_lunch_price : null,
            childLunchPrice: $tier->child_lunch_price ? (float) $tier->child_lunch_price : null,
            adultPmSnackPrice: $tier->adult_pm_snack_price ? (float) $tier->adult_pm_snack_price : null,
            childPmSnackPrice: $tier->child_pm_snack_price ? (float) $tier->child_pm_snack_price : null,
            adultDinnerPrice: $tier->adult_dinner_price ? (float) $tier->adult_dinner_price : null,
            childDinnerPrice: $tier->child_dinner_price ? (float) $tier->child_dinner_price : null,
            adultBreakfastPrice: $tier->adult_breakfast_price ? (float) $tier->adult_breakfast_price : null,
            childBreakfastPrice: $tier->child_breakfast_price ? (float) $tier->child_breakfast_price : null,
            effectiveFrom: $tier->effective_from,
            effectiveTo: $tier->effective_to
        );
    }

}
