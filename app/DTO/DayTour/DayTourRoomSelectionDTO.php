<?php

namespace App\DTO\DayTour;

use Spatie\LaravelData\Data;

class DayTourRoomSelectionDTO extends Data
{
    public function __construct(
        public string $room_id, // Changed to string (slug) to match overnight pattern
        public int $adults,
        public int $children,
        public bool $include_lunch = false,
        public bool $include_pm_snack = false,
        public float $lunch_cost = 0,
        public float $pm_snack_cost = 0,
        public float $meal_cost = 0
    ) {}

    public static function rules(): array
    {
        return [
            'room_id' => ['required', 'string', 'exists:rooms,slug'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['required', 'integer', 'min:0'],
            'include_lunch' => ['boolean'],
            'include_pm_snack' => ['boolean'],
            'lunch_cost' => ['numeric', 'min:0'],
            'pm_snack_cost' => ['numeric', 'min:0'],
            'meal_cost' => ['numeric', 'min:0'],
        ];
    }
}
