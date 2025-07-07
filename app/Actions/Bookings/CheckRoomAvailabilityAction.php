<?php

namespace App\Actions\Bookings;

use App\Contracts\Repositories\RoomRepositoryInterface;
use App\Data\BookingRoomData;
use App\Exceptions\RoomNotAvailableException;

class CheckRoomAvailabilityAction
{
    public function __construct(private RoomRepositoryInterface $roomRepo) {}

    /**
     * @param BookingRoomData[] $bookingRoomArr
     * @param string $check_in_date
     * @param string $check_out_date
     * @throws \Exception
     */
    public function execute(array $bookingRoomArr, string $check_in_date, string $check_out_date): void
    {
        // Count number of rooms being booked for each room_id
        $roomCounts = [];
        foreach ($bookingRoomArr as $roomData) {
            $roomCounts[$roomData->room_id] = ($roomCounts[$roomData->room_id] ?? 0) + 1;
        }
        foreach ($roomCounts as $room_id => $count) {
            $room = $this->roomRepo->getBySlug($room_id);
            $available = $this->roomRepo->getAvailableUnits($room->id, $check_in_date, $check_out_date);
            if ($count > $available) {
                throw new RoomNotAvailableException('Room not available for your selected dates or quantity.');
            }
        }
    }
}
