<?php

namespace App\Contracts\Repositories;

use App\Models\Booking;

interface BookingRepositoryInterface
{
    public function getByReferenceNumber(string $referenceNumber): Booking;
}
