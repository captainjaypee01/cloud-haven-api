<?php

namespace App\Actions\Bookings;

use App\Contracts\Services\BookingLockServiceInterface;

class SetBookingLockAction
{
    public function __construct(private BookingLockServiceInterface $lockService) {}

    public function execute($bookingId, $roomDataArr)
    {
        $lockData = [
            'rooms' => array_map(fn($r) => $r->toArray(), $roomDataArr),
            'expires_at' => now()->addMinutes(15)->timestamp
        ];
        $this->lockService->lock($bookingId, $lockData);
    }
}
