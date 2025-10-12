<?php

namespace App\DTO\Bookings;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Max;

class BookingModificationData extends Data
{
    public function __construct(
        public array $rooms,
        public ?string $modification_reason = null,
        public bool $send_email = false,
    ) {}

    public static function rules(): array
    {
        return [
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.room_id' => ['required', 'string', 'exists:rooms,slug'],
            'rooms.*.adults' => ['required', 'integer', 'min:1', 'max:10'],
            'rooms.*.children' => ['required', 'integer', 'min:0', 'max:10'],
            'rooms.*.total_guests' => ['required', 'integer', 'min:1', 'max:12'],
            'rooms.*.room_unit_id' => ['nullable', 'integer', 'exists:room_units,id'],
            'modification_reason' => ['nullable', 'string', 'max:500'],
            'send_email' => ['nullable', 'boolean'],
        ];
    }
}
