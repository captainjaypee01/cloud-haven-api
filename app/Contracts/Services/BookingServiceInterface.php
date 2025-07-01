<?php

namespace App\Contracts\Services;

use App\Dto\Bookings\BookingData;

interface BookingServiceInterface
{
    public function createBooking(BookingData $bookingData, ?int $userId = null);
}
