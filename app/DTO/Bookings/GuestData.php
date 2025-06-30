<?php

namespace App\DTO\Bookings;

use Spatie\LaravelData\Data;

class GuestData extends Data
{
    public function __construct(
        public string $guest_name,
        public string $guest_email,
        public ?string $guest_phone,
        public ?string $special_requests,
        public int $adults,
        public int $children
    ) {}
}
