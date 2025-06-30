<?php

namespace App\Contracts\Services;

use App\DTO\Bookings\GuestData;

interface BookingServiceInterface
{
    public function createBooking(array $roomData, GuestData $guest, ?int $userId = null);
}
