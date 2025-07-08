<?php

namespace App\Repositories;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Models\Booking;

class BookingRepository implements BookingRepositoryInterface
{
    public function getByReferenceNumber(string $referenceNumber): Booking
    {
        return Booking::with('bookingRooms.room', 'payments')->where('reference_number', $referenceNumber)->firstOrFail();
    }
}
