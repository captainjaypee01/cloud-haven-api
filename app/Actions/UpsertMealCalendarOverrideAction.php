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
                'date' => $dto->date,
                'is_active' => $dto->isActive,
                'note' => $dto->note,
            ];

            if ($dto->id) {
                $override = $this->overrideRepository->find($dto->id);
                if (!$override) {
                    throw new \Exception('Calendar override not found');
                }
                
                // Check if date is changing and would create a duplicate
                if (!$override->date->eq($dto->date)) {
                    $existing = $this->overrideRepository->getByProgramAndDate($dto->mealProgramId, $dto->date);
                    if ($existing && $existing->id !== $dto->id) {
                        throw new \InvalidArgumentException('An override already exists for this date');
                    }
                }
                
                return $this->overrideRepository->update($override, $data);
            } else {
                // Check for existing override on this date
                $existing = $this->overrideRepository->getByProgramAndDate($dto->mealProgramId, $dto->date);
                if ($existing) {
                    throw new \InvalidArgumentException('An override already exists for this date');
                }
                
                return $this->overrideRepository->create($data);
            }
        });
    }
}
