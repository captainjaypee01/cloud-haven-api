<?php

namespace App\Actions\Bookings;

use App\Dto\Bookings\BookingData;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use Illuminate\Support\Str;

class CreateBookingEntitiesAction
{
    public function execute(BookingData $bookingData, array $roomDataArr, $userId, $totals): Booking
    {
        $booking = Booking::create([
            'user_id' => $userId,
            'check_in_date' => $bookingData->check_in_date,
            'check_in_time' => '06:00',
            'check_out_date' => $bookingData->check_out_date,
            'check_out_time' => '04:00',
            'guest_name' => $bookingData->guest_name,
            'guest_email' => $bookingData->guest_email,
            'guest_phone' => $bookingData->guest_phone,
            'special_requests' => $bookingData->special_requests,
            'adults' => $bookingData->total_adults,
            'children' => $bookingData->total_children,
            'total_guests' => $bookingData->total_adults + $bookingData->total_children,
            'promo_id' => $bookingData->promo_id,
            'total_price' => $totals['total_room'],
            'meal_price' => $totals['meal_total'],
            'discount_amount' => 0,
            'final_price' => $totals['final_price'],
            'status' => 'pending',
            'reserved_until' => now()->addMinutes(15),
        ]);
        
        $roomIds = array_unique(array_map(fn($r) => $r->room_id, $roomDataArr));
        $rooms = Room::whereIn('slug', $roomIds)->get()->keyBy('slug');

        foreach ($roomDataArr as $roomData) {
            $room = $rooms[$roomData->room_id];
            BookingRoom::create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'price_per_night' => $room->price_per_night,
                'adults' => $roomData->adults,
                'children' => $roomData->children,
                'total_guests' => $roomData->adults + $roomData->children,
            ]);
        }
        return $booking;
    }
}
