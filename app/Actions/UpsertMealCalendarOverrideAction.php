<?php

namespace App\Actions;

use App\Contracts\Repositories\MealCalendarOverrideRepositoryInterface;
use App\DTO\MealOverrideDTO;
use App\Models\MealCalendarOverride;
use Illuminate\Support\Facades\DB;

class UpsertMealCalendarOverrideAction
{
    public function __construct(
        private MealCalendarOverrideRepositoryInterface $overrideRepository
    ) {}

    public function execute(MealOverrideDTO $dto): MealCalendarOverride
    {
        return DB::transaction(function () use ($dto) {
            $data = [
                'meal_program_id' => $dto->mealProgramId,
                'override_type' => $dto->overrideType,
                'date' => $dto->date,
                'month' => $dto->month,
                'year' => $dto->year,
                'is_active' => $dto->isActive,
                'note' => $dto->note,
            ];

            if ($dto->id) {
                $override = $this->overrideRepository->find($dto->id);
                if (!$override) {
                    throw new \Exception('Calendar override not found');
                }
                
                // Check for duplicates based on override type
                if ($dto->overrideType === 'date' && $dto->date) {
                    if (!$override->date || !$override->date->eq($dto->date)) {
                        $existing = $this->overrideRepository->getByProgramAndDate($dto->mealProgramId, $dto->date);
                        if ($existing && $existing->id !== $dto->id) {
                            throw new \InvalidArgumentException('An override already exists for this date');
                        }
                    }
                } elseif ($dto->overrideType === 'month' && $dto->month && $dto->year) {
                    if ($override->month !== $dto->month || $override->year !== $dto->year) {
                        $existing = $this->overrideRepository->getByProgramAndMonth($dto->mealProgramId, $dto->month, $dto->year);
                        if ($existing && $existing->id !== $dto->id) {
                            throw new \InvalidArgumentException('An override already exists for this month');
                        }
                    }
                }
                
                return $this->overrideRepository->update($override, $data);
            } else {
                // Check for existing override based on type
                if ($dto->overrideType === 'date' && $dto->date) {
                    $existing = $this->overrideRepository->getByProgramAndDate($dto->mealProgramId, $dto->date);
                    if ($existing) {
                        throw new \InvalidArgumentException('An override already exists for this date');
                    }
                } elseif ($dto->overrideType === 'month' && $dto->month && $dto->year) {
                    $existing = $this->overrideRepository->getByProgramAndMonth($dto->mealProgramId, $dto->month, $dto->year);
                    if ($existing) {
                        throw new \InvalidArgumentException('An override already exists for this month');
                    }
                }
                
                return $this->overrideRepository->create($data);
            }
        });
    }
}
