<?php

namespace App\DTO\DayTour;

use Spatie\LaravelData\Data;

class DayTourOptionsDTO extends Data
{
    public function __construct(
        public bool $include_lunch = false,
        public bool $include_pm_snack = false
    ) {}

    public static function rules(): array
    {
        return [
            'include_lunch' => ['required', 'boolean'],
            'include_pm_snack' => ['required', 'boolean'],
        ];
    }
}
