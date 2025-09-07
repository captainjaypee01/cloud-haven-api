<?php

namespace App\DTO\DayTour;

use Spatie\LaravelData\Data;

class DayTourQuoteRequestDTO extends Data
{
    /**
     * @param string $date
     * @param DayTourRoomSelectionDTO[] $selections
     * @param DayTourOptionsDTO $options
     */
    public function __construct(
        public string $date,
        public array $selections,
        public DayTourOptionsDTO $options
    ) {}

    public static function rules(): array
    {
        return [
            'date' => ['required', 'date', 'after_or_equal:today'],
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.room_id' => ['required', 'integer', 'exists:rooms,id'],
            'selections.*.units' => ['required', 'integer', 'min:1'],
            'selections.*.adults' => ['required', 'integer', 'min:1'],
            'selections.*.children' => ['required', 'integer', 'min:0'],
            'options' => ['required', 'array'],
            'options.include_lunch' => ['required', 'boolean'],
            'options.include_pm_snack' => ['required', 'boolean'],
        ];
    }
}
