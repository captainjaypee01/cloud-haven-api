<?php

namespace App\Actions\Bookings;

use App\Actions\ComputeMealQuoteAction;
use App\Models\Room;

class CalculateBookingTotalAction
{
    public function __construct(private ComputeMealQuoteAction $computeMealQuoteAction) {}

    public function execute(array $bookingRoomArr, string $check_in_date, string $check_out_date, int $adults, int $children): array
    {
        $roomIds = array_unique(array_map(fn($r) => $r->room_id, $bookingRoomArr));
        $rooms = Room::whereIn('slug', $roomIds)->get()->keyBy('slug');

        $totalRoom = 0;
        $nights = \Carbon\Carbon::parse($check_in_date)->diffInDays($check_out_date);
        foreach ($bookingRoomArr as $roomData) {
            $room = $rooms[$roomData->room_id];
            $totalRoom += $room->price_per_night * $nights;
        }

        // Use new meal program system for dynamic pricing
        $mealQuote = $this->computeMealQuoteAction->execute($check_in_date, $check_out_date, $adults, $children);
        $mealTotal = $mealQuote->mealSubtotal;

        $finalTotal = $totalRoom + $mealTotal;
        return [
            'total_room' => $totalRoom,
            'meal_total' => $mealTotal,
            'final_price' => $finalTotal,
            'meal_quote' => $mealQuote // Include meal quote for email templates
        ];
    }
}
