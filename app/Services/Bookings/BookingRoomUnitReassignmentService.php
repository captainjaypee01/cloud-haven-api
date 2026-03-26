<?php

namespace App\Services\Bookings;

use App\Contracts\Repositories\RoomUnitRepositoryInterface;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingRoomUnitReassignmentService
{
    public function __construct(
        private RoomUnitRepositoryInterface $roomUnitRepo
    ) {}

    /**
     * Ensure each booking room's unit (or a substitute) is available for the given stay window.
     */
    public function reassignRoomUnitsForBooking(Booking $booking, string $newCheckIn, string $newCheckOut): void
    {
        $booking->load('bookingRooms.roomUnit.room');

        foreach ($booking->bookingRooms as $bookingRoom) {
            $currentUnitId = $bookingRoom->room_unit_id;
            $roomId = $bookingRoom->room_id;

            if ($currentUnitId && $this->isRoomUnitAvailable($currentUnitId, $newCheckIn, $newCheckOut, $booking->id)) {
                Log::info('Room unit still available for date range', [
                    'booking_id' => $booking->id,
                    'booking_room_id' => $bookingRoom->id,
                    'room_unit_id' => $currentUnitId,
                    'new_check_in' => $newCheckIn,
                    'new_check_out' => $newCheckOut,
                ]);
                continue;
            }

            if (!$currentUnitId) {
                $alternativeUnit = $this->findAlternativeRoomUnit($roomId, $newCheckIn, $newCheckOut, $booking->id);
                if (!$alternativeUnit) {
                    throw new RoomNotAvailableException(
                        'No available room units found for room type. Please choose different dates.'
                    );
                }
                $bookingRoom->room_unit_id = $alternativeUnit->id;
                $bookingRoom->save();
                Log::info('Room unit assigned for booking room without unit', [
                    'booking_id' => $booking->id,
                    'booking_room_id' => $bookingRoom->id,
                    'new_unit_id' => $alternativeUnit->id,
                ]);
                continue;
            }

            $alternativeUnit = $this->findAlternativeRoomUnit($roomId, $newCheckIn, $newCheckOut, $booking->id);

            if (!$alternativeUnit) {
                throw new RoomNotAvailableException(
                    'No available room units found for room type. Please choose different dates.'
                );
            }

            $oldUnitId = $bookingRoom->room_unit_id;
            $bookingRoom->room_unit_id = $alternativeUnit->id;
            $bookingRoom->save();

            Log::info('Room unit reassigned for date range', [
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

    public function isRoomUnitAvailable(int $unitId, string $checkIn, string $checkOut, int $excludeBookingId): bool
    {
        $query = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->where('booking_rooms.room_unit_id', $unitId)
            ->where('bookings.id', '!=', $excludeBookingId)
            ->whereIn('bookings.status', ['paid', 'downpayment'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where(function ($subQ) use ($checkIn, $checkOut) {
                    $subQ->where('bookings.check_in_date', '<', $checkOut)
                        ->where('bookings.check_out_date', '>', $checkIn);
                })->orWhere(function ($subQ) use ($checkIn) {
                    $subQ->where('bookings.booking_type', 'day_tour')
                        ->where('bookings.check_in_date', $checkIn);
                });
            });

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

        $pendingWithoutPaymentQuery = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->leftJoin('payments', 'bookings.id', '=', 'payments.booking_id')
            ->where('booking_rooms.room_unit_id', $unitId)
            ->where('bookings.id', '!=', $excludeBookingId)
            ->where('bookings.status', 'pending')
            ->whereNull('payments.booking_id')
            ->where('bookings.reserved_until', '>', now())
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where(function ($subQ) use ($checkIn, $checkOut) {
                    $subQ->where('bookings.check_in_date', '<', $checkOut)
                        ->where('bookings.check_out_date', '>', $checkIn);
                })->orWhere(function ($subQ) use ($checkIn) {
                    $subQ->where('bookings.booking_type', 'day_tour')
                        ->where('bookings.check_in_date', $checkIn);
                });
            });

        $isOccupied = $query->exists() || $pendingWithPaymentQuery->exists() || $pendingWithoutPaymentQuery->exists();

        return ! $isOccupied;
    }

    private function findAlternativeRoomUnit(int $roomId, string $checkIn, string $checkOut, int $excludeBookingId): ?object
    {
        $allUnits = $this->roomUnitRepo->getAvailableUnitsForRoom($roomId);

        foreach ($allUnits as $unit) {
            if ($this->isRoomUnitAvailable($unit->id, $checkIn, $checkOut, $excludeBookingId)) {
                return $unit;
            }
        }

        return null;
    }
}
