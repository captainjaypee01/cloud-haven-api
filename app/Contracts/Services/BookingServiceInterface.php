<?php
namespace App\Contracts\Services;

use App\Models\Booking;

interface BookingServiceInterface
{
    public function create(array $data): Booking;
}
