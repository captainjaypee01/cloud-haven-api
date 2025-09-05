<?php

namespace App\Actions;

use App\Contracts\Repositories\MealPricingTierRepositoryInterface;
use App\DTO\MealPricingTierDTO;
use App\Models\MealPricingTier;
use Illuminate\Support\Facades\DB;

class UpsertMealPricingTierAction
{
    public function __construct(
        private MealPricingTierRepositoryInterface $tierRepository
    ) {}

    public function execute(MealPricingTierDTO $dto): MealPricingTier
    {
        return DB::transaction(function () use ($dto) {
            $data = [
                'meal_program_id' => $dto->mealProgramId,
                'currency' => $dto->currency,
                'adult_price' => $dto->adultPrice,
                'child_price' => $dto->childPrice,
                'effective_from' => $dto->effectiveFrom,
                'effective_to' => $dto->effectiveTo,
            ];

            // Validate data
            $this->validateData($dto);

            if ($dto->id) {
                $tier = $this->tierRepository->find($dto->id);
                if (!$tier) {
                    throw new \Exception('Pricing tier not found');
                }
                return $this->tierRepository->update($tier, $data);
            } else {
                return $this->tierRepository->create($data);
            }
        });
    }

    private function validateData(MealPricingTierDTO $dto): void
    {
        if ($dto->adultPrice < 0) {
            throw new \InvalidArgumentException('Adult price cannot be negative');
        }

        if ($dto->childPrice < 0) {
            throw new \InvalidArgumentException('Child price cannot be negative');
        }

        if (strlen($dto->currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter code');
        }

        if ($dto->effectiveFrom && $dto->effectiveTo && $dto->effectiveTo->lt($dto->effectiveFrom)) {
            throw new \InvalidArgumentException('Effective to date must be after effective from date');
        }

        // Check for overlapping tiers
        $existingTiers = $this->tierRepository->getByProgramId($dto->mealProgramId);
        
        foreach ($existingTiers as $existingTier) {
            if ($dto->id && $existingTier->id === $dto->id) {
                continue; // Skip self when updating
            }

            if ($this->tiersOverlap($dto, $existingTier)) {
                throw new \InvalidArgumentException('Pricing tier dates overlap with an existing tier');
            }
        }
    }

    private function tiersOverlap(MealPricingTierDTO $new, MealPricingTier $existing): bool
    {
        // If either tier has no dates, they could potentially overlap
        if (!$new->effectiveFrom && !$new->effectiveTo) {
            return true; // Open-ended tier overlaps with everything
        }

        if (!$existing->effective_from && !$existing->effective_to) {
            return true; // Existing open-ended tier
        }

        // Check for actual date overlaps
        $newStart = $new->effectiveFrom;
        $newEnd = $new->effectiveTo;
        $existingStart = $existing->effective_from;
        $existingEnd = $existing->effective_to;

        // Handle open-ended ranges
        if (!$newEnd) {
            return !$existingEnd || $newStart->lte($existingEnd);
        }

        if (!$existingEnd) {
            return !$newEnd || $existingStart->lte($newEnd);
        }

        // Both have end dates, check for overlap
        return $newStart->lte($existingEnd) && $newEnd->gte($existingStart);
    }
}
