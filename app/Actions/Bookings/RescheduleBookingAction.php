<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Services\Bookings\BookingRoomUnitReassignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RescheduleBookingAction
{
    public function __construct(
        private BookingRoomUnitReassignmentService $roomUnitReassignment
    ) {}

    /**
     * Reschedule a booking with automatic room unit reassignment if needed
     */
    public function execute(Booking $booking, string $newCheckIn, string $newCheckOut): Booking
    {
        DB::beginTransaction();

        try {
            $oldCheckIn = $booking->check_in_date;
            $oldCheckOut = $booking->check_out_date;

            $booking->update([
                'check_in_date' => $newCheckIn,
                'check_out_date' => $newCheckOut,
            ]);

            $this->roomUnitReassignment->reassignRoomUnitsForBooking($booking, $newCheckIn, $newCheckOut);

            DB::commit();

            Log::info('Booking rescheduled with room unit reassignment', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'old_check_in' => $oldCheckIn,
                'old_check_out' => $oldCheckOut,
                'new_check_in' => $newCheckIn,
                'new_check_out' => $newCheckOut,
            ]);

            return $booking->refresh();

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
