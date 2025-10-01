<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Services\RoomUnitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmBookingAction
{
    public function __construct(
        private readonly RoomUnitService $roomUnitService
    ) {}

    /**
     * Confirm a booking by verifying room units are assigned to each booking room.
     * This should be called when a booking moves to 'paid' or 'downpayment' status.
     * Note: Room units are now assigned immediately during booking creation, so this
     * method mainly verifies that all units are properly assigned.
     */
    public function execute(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            // Load booking rooms with room relationship
            $booking->load('bookingRooms.room');
            
            $allAssigned = true;
            
            foreach ($booking->bookingRooms as $bookingRoom) {
                // Check if unit is already assigned
                if ($bookingRoom->room_unit_id) {
                    // Unit already assigned
                    continue;
                }

                // Try to assign a room unit (fallback for bookings created before this update)
                $assignedUnit = $this->roomUnitService->assignUnitToBooking(
                    $bookingRoom->room_id,
                    $booking->check_in_date,
                    $booking->check_out_date
                );

                if ($assignedUnit) {
                    // Update the booking room with the assigned unit
                    $bookingRoom->room_unit_id = $assignedUnit->id;
                    $bookingRoom->save();
                    
                    // Unit assigned successfully
                } else {
                    Log::error("Failed to assign room unit for booking room {$bookingRoom->id} (room type: {$bookingRoom->room->name}) for booking {$booking->reference_number}");
                    $allAssigned = false;
                }
            }

            return $allAssigned;
        });
    }

    /**
     * Release room units when a booking is cancelled.
     */
    public function releaseUnits(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            foreach ($booking->bookingRooms as $bookingRoom) {
                if ($bookingRoom->room_unit_id && $bookingRoom->roomUnit) {
                    $this->roomUnitService->releaseUnit($bookingRoom->roomUnit);
                    // Unit released for cancelled booking
                }
            }
        });
    }
}
