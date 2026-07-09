<?php

namespace App\Actions\Bookings;

use App\DTO\RoomQuoteDTO;
use App\Models\Booking;
use Carbon\Carbon;

class SyncBookingRoomLineTotalsFromQuoteAction
{
    public function execute(Booking $booking, RoomQuoteDTO $roomQuote, string $checkIn, string $checkOut): void
    {
        $booking->load('bookingRooms');
        $lineTotals = $roomQuote->getLineTotalsByIndex();
        $nights = max(1, Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)));
        $bookingRooms = $booking->bookingRooms->sortBy('id')->values();

        foreach ($bookingRooms as $index => $bookingRoom) {
            $lineTotal = $lineTotals[$index] ?? (float) $bookingRoom->total_price;
            $avgNightly = $nights > 0 ? round($lineTotal / $nights, 2) : (float) $bookingRoom->price_per_night;

            $bookingRoom->update([
                'price_per_night' => $avgNightly,
                'total_price' => $lineTotal,
            ]);
        }
    }
}
