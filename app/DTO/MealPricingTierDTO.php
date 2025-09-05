<?php

namespace App\DTO;

use App\Models\MealPricingTier;
use Carbon\Carbon;

class MealPricingTierDTO
{
    public function __construct(
        public ?int $id,
        public int $mealProgramId,
        public string $currency,
        public float $adultPrice,
        public float $childPrice,
        public ?Carbon $effectiveFrom,
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
            effectiveFrom: $tier->effective_from,
            effectiveTo: $tier->effective_to
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'meal_program_id' => $this->mealProgramId,
            'currency' => $this->currency,
            'adult_price' => round($this->adultPrice, 2),
            'child_price' => round($this->childPrice, 2),
            'effective_from' => $this->effectiveFrom?->format('Y-m-d'),
            'effective_to' => $this->effectiveTo?->format('Y-m-d'),
        ];
    }
}
