<?php

namespace App\DTO\DayTour;

use Spatie\LaravelData\Data;

class DayTourBookingModificationData extends Data
{
    public function __construct(
        public array $rooms,
        public string $modification_reason,
        public bool $send_email = false
    ) {}

    public static function rules(): array
    {
        return [
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.room_id' => ['required', 'string', 'exists:rooms,slug'],
            'rooms.*.room_unit_id' => ['nullable', 'integer', 'exists:room_units,id'],
            'rooms.*.adults' => ['required', 'integer', 'min:1'],
            'rooms.*.children' => ['required', 'integer', 'min:0'],
            'rooms.*.include_lunch' => ['required', 'boolean'],
            'rooms.*.include_pm_snack' => ['required', 'boolean'],
            'modification_reason' => ['required', 'string', 'max:1000'],
            'send_email' => ['boolean'],
        ];
    }
}
