<?php

namespace App\Actions\Bookings;

use App\Contracts\Services\BookingLockServiceInterface;

class SetBookingLockAction
{
    public function __construct(private BookingLockServiceInterface $lockService) {}

    public function execute($bookingId, $bookingRoomArr, $check_in_date, $check_out_date)
    {
        $lockData = [
            'rooms' => array_map(fn($r) => (array) $r, $bookingRoomArr),
            'check_in_date' => $check_in_date,
            'check_out_date' => $check_out_date,
            'expires_at' => now()->addMinutes(15)->timestamp
        ];
        $this->lockService->lock($bookingId, $lockData);
    }
}
