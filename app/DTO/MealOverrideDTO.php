<?php

namespace App\DTO;

use App\Models\MealCalendarOverride;
use Carbon\Carbon;

class MealOverrideDTO
{
    public function __construct(
        public ?int $id,
        public int $mealProgramId,
        public Carbon $date,
        public bool $isActive,
        public ?string $note
    ) {}

    public static function fromModel(MealCalendarOverride $override): self
    {
        return new self(
            id: $override->id,
            mealProgramId: $override->meal_program_id,
            date: $override->date,
            isActive: $override->is_active,
            note: $override->note
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'meal_program_id' => $this->mealProgramId,
            'date' => $this->date->format('Y-m-d'),
            'is_active' => $this->isActive,
            'note' => $this->note,
        ];
    }
}
