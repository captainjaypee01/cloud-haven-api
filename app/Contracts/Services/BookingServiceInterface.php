<?php

namespace App\Contracts\Services;

use App\Dto\Bookings\BookingData;
use App\Models\Booking;

interface BookingServiceInterface
{
    public function createBooking(BookingData $bookingData, ?int $userId = null);
    public function showByReferenceNumber(string $referenceNumber): Booking;
    public function markPaid(Booking $booking): void;
    public function markPaymentFailed(Booking $booking): void;
}
