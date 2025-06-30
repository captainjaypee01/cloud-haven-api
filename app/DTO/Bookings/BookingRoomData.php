<?php

namespace App\DTO\Bookings;

use Spatie\LaravelData\Data;

class BookingRoomData extends Data
{
    public function __construct(
        public int $room_id,
        public int $adults,
        public int $children
    ) {}
}
