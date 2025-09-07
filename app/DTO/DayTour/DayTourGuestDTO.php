<?php

namespace App\DTO\DayTour;

use Spatie\LaravelData\Data;

class DayTourGuestDTO extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
