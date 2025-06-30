<?php

namespace App\Services\Bookings;

use App\Actions\Bookings\CalculateBookingTotalAction;
use App\Actions\Bookings\CheckRoomAvailabilityAction;
use App\Actions\Bookings\CreateBookingEntitiesAction;
use App\Actions\Bookings\SetBookingLockAction;
use App\Dto\Bookings\BookingData;
use App\DTO\Bookings\BookingRoomData;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private CheckRoomAvailabilityAction $checkAvailability,
        private CalculateBookingTotalAction $calcTotal,
        private CreateBookingEntitiesAction $createEntities,
        private SetBookingLockAction $setLock
    ) {}
    public function createBooking(BookingData $bookingData, ?int $userId = null)
    {
        $roomDataArr = array_map(fn($rd) => BookingRoomData::from($rd), $bookingData->rooms);

        return DB::transaction(function () use ($bookingData, $roomDataArr, $userId) {
            $this->checkAvailability->execute($roomDataArr, $bookingData->check_in_date, $bookingData->check_out_date);
            $totals = $this->calcTotal->execute($roomDataArr, $bookingData->adults, $bookingData->children, $bookingData->check_in_date, $bookingData->check_out_date);
            $booking = $this->createEntities->execute($bookingData, $roomDataArr, $userId, $totals);
            $this->setLock->execute($booking->id, $roomDataArr, $bookingData->check_in_date, $bookingData->check_out_date);
            return $booking;
        });
    }
}
