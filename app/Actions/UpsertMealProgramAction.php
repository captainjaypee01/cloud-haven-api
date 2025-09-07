<?php

namespace App\Actions;

use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\DTO\MealProgramDTO;
use App\Models\MealProgram;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpsertMealProgramAction
{
    public function __construct(
        private MealProgramRepositoryInterface $programRepository
    ) {}

    public function execute(MealProgramDTO $dto): MealProgram
    {
        return DB::transaction(function () use ($dto) {
            $data = [
                'name' => $dto->name,
                'status' => $dto->status,
                'scope_type' => $dto->scopeType,
                'date_start' => $dto->dateStart,
                'date_end' => $dto->dateEnd,
                'months' => $dto->months,
                'weekdays' => $dto->weekdays,
                'weekend_definition' => $dto->weekendDefinition,
                'pm_snack_policy' => $dto->pmSnackPolicy,
                'inactive_label' => $dto->inactiveLabel,
                'notes' => $dto->notes,
            ];

            // Validate data based on scope type
            $this->validateScopeData($dto);

            if ($dto->id) {
                $program = $this->programRepository->find($dto->id);
                if (!$program) {
                    throw new \Exception('Meal program not found');
                }
                return $this->programRepository->update($program, $data);
            } else {
                return $this->programRepository->create($data);
            }
        });
    }

    private function validateScopeData(MealProgramDTO $dto): void
    {
        switch ($dto->scopeType) {
            case 'date_range':
                if (!$dto->dateStart || !$dto->dateEnd) {
                    throw new \InvalidArgumentException('Date range programs must have start and end dates');
                }
                if ($dto->dateEnd->lt($dto->dateStart)) {
                    throw new \InvalidArgumentException('End date must be after start date');
                }
                break;
                
            case 'months':
                if (empty($dto->months)) {
                    throw new \InvalidArgumentException('Month-based programs must have at least one month selected');
                }
                foreach ($dto->months as $month) {
                    if (!is_int($month) || $month < 1 || $month > 12) {
                        throw new \InvalidArgumentException('Invalid month value: ' . $month);
                    }
                }
                break;
                
            case 'weekly':
                if ($dto->weekendDefinition === 'CUSTOM' && empty($dto->weekdays)) {
                    throw new \InvalidArgumentException('Custom weekly programs must have weekdays selected');
                }
                if ($dto->weekdays) {
                    $validDays = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
                    foreach ($dto->weekdays as $day) {
                        if (!in_array($day, $validDays)) {
                            throw new \InvalidArgumentException('Invalid weekday: ' . $day);
                        }
                    }
                }
                break;
                
            case 'composite':
                // Composite can have any combination, no specific validation needed
                break;
                
            case 'always':
                // Always active, no additional data needed
                break;
                
            default:
                throw new \InvalidArgumentException('Invalid scope type: ' . $dto->scopeType);
        }
    }
}
