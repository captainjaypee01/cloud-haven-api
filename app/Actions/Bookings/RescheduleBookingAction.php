<?php

namespace App\Actions\Bookings;

use App\Contracts\Repositories\RoomRepositoryInterface;
use App\Contracts\Repositories\RoomUnitRepositoryInterface;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\BookingRoom;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RescheduleBookingAction
{
    public function __construct(
        private RoomRepositoryInterface $roomRepo,
        private RoomUnitRepositoryInterface $roomUnitRepo
    ) {}

    /**
     * Reschedule a booking with automatic room unit reassignment if needed
     */
    public function execute(Booking $booking, string $newCheckIn, string $newCheckOut): Booking
    {
        DB::beginTransaction();
        
        try {
            // Store original dates for logging
            $oldCheckIn = $booking->check_in_date;
            $oldCheckOut = $booking->check_out_date;
            
            // Update booking dates
            $booking->update([
                'check_in_date' => $newCheckIn,
                'check_out_date' => $newCheckOut,
            ]);
            
            // Handle room unit reassignment for each booking room
            $this->reassignRoomUnits($booking, $newCheckIn, $newCheckOut);
            
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
    
    /**
     * Reassign room units for each booking room, finding alternatives if current units are occupied
     */
    private function reassignRoomUnits(Booking $booking, string $newCheckIn, string $newCheckOut): void
    {
        $booking->load('bookingRooms.roomUnit.room');
        
        foreach ($booking->bookingRooms as $bookingRoom) {
            $currentUnitId = $bookingRoom->room_unit_id;
            $roomId = $bookingRoom->room_id;
            
            // Check if current room unit is available for new dates
            if ($this->isRoomUnitAvailable($currentUnitId, $newCheckIn, $newCheckOut, $booking->id)) {
                // Current unit is available, no need to change
                Log::info('Room unit still available for reschedule', [
                    'booking_id' => $booking->id,
                    'booking_room_id' => $bookingRoom->id,
                    'room_unit_id' => $currentUnitId,
                    'new_check_in' => $newCheckIn,
                    'new_check_out' => $newCheckOut,
                ]);
                continue;
            }
            
            // Current unit is not available, find an alternative
            $alternativeUnit = $this->findAlternativeRoomUnit($roomId, $newCheckIn, $newCheckOut, $booking->id);
            
            if (!$alternativeUnit) {
                throw new RoomNotAvailableException(
                    "No available room units found for room type. Please choose different dates."
                );
            }
            
            // Reassign to alternative unit
            $oldUnitId = $bookingRoom->room_unit_id;
            $bookingRoom->room_unit_id = $alternativeUnit->id;
            $bookingRoom->save();
            
            Log::info('Room unit reassigned during reschedule', [
                'booking_id' => $booking->id,
                'booking_room_id' => $bookingRoom->id,
                'old_unit_id' => $oldUnitId,
                'new_unit_id' => $alternativeUnit->id,
                'new_unit_number' => $alternativeUnit->unit_number,
                'new_check_in' => $newCheckIn,
                'new_check_out' => $newCheckOut,
            ]);
        }
    }
    
    /**
     * Check if a specific room unit is available for the given dates
     */
    private function isRoomUnitAvailable(int $unitId, string $checkIn, string $checkOut, int $excludeBookingId): bool
    {
        $query = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->where('booking_rooms.room_unit_id', $unitId)
            ->where('bookings.id', '!=', $excludeBookingId)
            ->whereIn('bookings.status', ['paid', 'downpayment'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                // Handle both overnight and day tour bookings
                $q->where(function ($subQ) use ($checkIn, $checkOut) {
                    // Overnight bookings: check_in < endDate AND check_out > startDate
                    $subQ->where('bookings.check_in_date', '<', $checkOut)
                        ->where('bookings.check_out_date', '>', $checkIn);
                })->orWhere(function ($subQ) use ($checkIn) {
                    // Day tour bookings: check_in_date = startDate (same day booking)
                    $subQ->where('bookings.booking_type', 'day_tour')
                        ->where('bookings.check_in_date', $checkIn);
                });
            });
        
        // Also check pending bookings with payment records
        $pendingWithPaymentQuery = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->where('booking_rooms.room_unit_id', $unitId)
            ->where('bookings.id', '!=', $excludeBookingId)
            ->where('bookings.status', 'pending')
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where(function ($subQ) use ($checkIn, $checkOut) {
                    $subQ->where('bookings.check_in_date', '<', $checkOut)
                        ->where('bookings.check_out_date', '>', $checkIn);
                })->orWhere(function ($subQ) use ($checkIn) {
                    $subQ->where('bookings.booking_type', 'day_tour')
                        ->where('bookings.check_in_date', $checkIn);
                });
            });
        
        // Check pending bookings without payment records (only if within reserved_until period)
        $pendingWithoutPaymentQuery = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->leftJoin('payments', 'bookings.id', '=', 'payments.booking_id')
            ->where('booking_rooms.room_unit_id', $unitId)
            ->where('bookings.id', '!=', $excludeBookingId)
            ->where('bookings.status', 'pending')
            ->whereNull('payments.booking_id') // No payment record exists
            ->where('bookings.reserved_until', '>', now()) // Still within reserved period
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where(function ($subQ) use ($checkIn, $checkOut) {
                    $subQ->where('bookings.check_in_date', '<', $checkOut)
                        ->where('bookings.check_out_date', '>', $checkIn);
                })->orWhere(function ($subQ) use ($checkIn) {
                    $subQ->where('bookings.booking_type', 'day_tour')
                        ->where('bookings.check_in_date', $checkIn);
                });
            });
        
        // Check if unit is occupied
        $isOccupied = $query->exists() || $pendingWithPaymentQuery->exists() || $pendingWithoutPaymentQuery->exists();
        
        return !$isOccupied;
    }
    
    /**
     * Find an alternative room unit from the same room type that's available for the given dates
     */
    private function findAlternativeRoomUnit(int $roomId, string $checkIn, string $checkOut, int $excludeBookingId): ?object
    {
        // Get all room units for this room type
        $allUnits = $this->roomUnitRepo->getAvailableUnitsForRoom($roomId);
        
        foreach ($allUnits as $unit) {
            if ($this->isRoomUnitAvailable($unit->id, $checkIn, $checkOut, $excludeBookingId)) {
                return $unit;
            }
        }
        
        return null;
    }
}
