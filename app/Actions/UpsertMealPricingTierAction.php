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
        // If both tiers have no dates (null), they overlap
        if (!$new->effectiveFrom && !$new->effectiveTo && !$existing->effective_from && !$existing->effective_to) {
            return true;
        }

        // If one tier has no dates and the other has dates, they don't overlap
        // (null dates means "always effective" and doesn't conflict with specific date ranges)
        if ((!$new->effectiveFrom && !$new->effectiveTo) && ($existing->effective_from || $existing->effective_to)) {
            return false;
        }

        if ((!$existing->effective_from && !$existing->effective_to) && ($new->effectiveFrom || $new->effectiveTo)) {
            return false;
        }

        // If both have dates, check for actual date overlaps
        $newStart = $new->effectiveFrom;
        $newEnd = $new->effectiveTo;
        $existingStart = $existing->effective_from;
        $existingEnd = $existing->effective_to;

        // Handle cases where one or both dates are null
        if (!$newStart && !$newEnd) {
            return false; // Already handled above
        }

        if (!$existingStart && !$existingEnd) {
            return false; // Already handled above
        }

        // Handle open-ended ranges (only one date is null)
        if (!$newEnd && $newStart) {
            // New tier starts from a date and has no end
            return !$existingEnd || $newStart->lte($existingEnd);
        }

        if (!$existingEnd && $existingStart) {
            // Existing tier starts from a date and has no end
            return !$newEnd || $existingStart->lte($newEnd);
        }

        if (!$newStart && $newEnd) {
            // New tier has no start but has an end
            return !$existingStart || $newEnd->gte($existingStart);
        }

        if (!$existingStart && $existingEnd) {
            // Existing tier has no start but has an end
            return !$newStart || $existingEnd->gte($newStart);
        }

        // Both have start and end dates, check for overlap
        return $newStart->lte($existingEnd) && $newEnd->gte($existingStart);
    }
}
