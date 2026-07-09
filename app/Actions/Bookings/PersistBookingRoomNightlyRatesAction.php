<?php

namespace App\Actions\Bookings;

use App\DTO\RoomQuoteDTO;
use App\Models\Booking;
use App\Models\BookingRoomNightlyRate;

class PersistBookingRoomNightlyRatesAction
{
    public function execute(Booking $booking, RoomQuoteDTO $roomQuote): void
    {
        BookingRoomNightlyRate::where('booking_id', $booking->id)->delete();

        $booking->load('bookingRooms');
        $bookingRooms = $booking->bookingRooms->sortBy('id')->values();

        foreach ($roomQuote->nights as $night) {
            foreach ($night->rooms as $index => $roomNight) {
                $bookingRoom = $bookingRooms->get($index);

                if (! $bookingRoom) {
                    continue;
                }

                BookingRoomNightlyRate::create([
                    'booking_id' => $booking->id,
                    'booking_room_id' => $bookingRoom->id,
                    'room_id' => $roomNight->roomId,
                    'date' => $night->date,
                    'rate' => $roomNight->rate,
                ]);
            }
        }
    }
}
