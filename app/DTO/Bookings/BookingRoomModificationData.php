<?php

namespace App\DTO\Bookings;

use Spatie\LaravelData\Data;

class BookingRoomModificationData extends Data
{
    public function __construct(
        public string $room_id,
        public int $adults,
        public int $children,
        public int $total_guests,
        public ?int $room_unit_id = null,
    ) {}
}
