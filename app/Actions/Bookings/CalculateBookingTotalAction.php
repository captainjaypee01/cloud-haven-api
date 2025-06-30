<?php

namespace App\Actions\Bookings;

use App\Contracts\Services\MealPriceServiceInterface;
use App\Models\Room;

class CalculateBookingTotalAction
{
    public function __construct(private MealPriceServiceInterface $mealPriceService) {}

    public function execute(array $bookingRoomArr, string $check_in_date, string $check_out_date, int $adults, int $children): array
    {
        $roomIds = array_unique(array_map(fn($r) => $r->room_id, $bookingRoomArr));
        $rooms = Room::whereIn('id', $roomIds)->get()->keyBy('id');

        $totalRoom = 0;
        // $nights = (new \DateTime($check_in_date))->diff(new \DateTime($check_out_date))->days;
        $nights = \Carbon\Carbon::parse($check_in_date)->diffInDays($check_out_date);
        foreach ($bookingRoomArr as $roomData) {
            $room = $rooms[$roomData->room_id];
            $totalRoom += $room->price_per_night * $nights;
        }
        $mealTotal =
            $adults * $this->mealPriceService->getPriceForCategory('adult') +
            $children * $this->mealPriceService->getPriceForCategory('children');

        $finalTotal = $totalRoom + $mealTotal;
        return [
            'total_room' => $totalRoom,
            'meal_total' => $mealTotal,
            'final_price' => $finalTotal
        ];
    }
}
