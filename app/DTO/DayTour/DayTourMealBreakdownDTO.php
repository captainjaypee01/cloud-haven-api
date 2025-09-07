<?php

namespace App\DTO\DayTour;

class DayTourMealBreakdownDTO
{
    public function __construct(
        public ?DayTourMealLineItemDTO $lunch = null,
        public ?DayTourMealLineItemDTO $pmSnack = null
    ) {}

    public function toArray(): array
    {
        return [
            'lunch' => $this->lunch ? $this->lunch->toArray() : null,
            'pm_snack' => $this->pmSnack ? $this->pmSnack->toArray() : null,
        ];
    }
}
