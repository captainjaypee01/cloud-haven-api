<?php

namespace App\Actions\Bookings;

use App\Contracts\Services\BookingLockServiceInterface;

class SetBookingLockAction
{
    public function __construct(private BookingLockServiceInterface $lockService) {}

    public function execute($bookingId, $bookingRoomArr, $check_in_date, $check_out_date)
    {
        $holdHours = config('booking.reservation_hold_duration_hours', 2);
        $lockData = [
            'rooms' => array_map(fn($r) => (array) $r, $bookingRoomArr),
            'check_in_date' => $check_in_date,
            'check_out_date' => $check_out_date,
            'expires_at' => now()->addHours($holdHours)->timestamp
        ];
        $this->lockService->lock($bookingId, $lockData);
    }
}
