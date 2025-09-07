<?php

namespace App\DTO\DayTour;

class DayTourQuoteItemDTO
{
    public function __construct(
        public string $roomId, // Changed to string (slug) to match overnight pattern
        public float $baseSubtotal,
        public float $extraGuestFee,
        public DayTourMealBreakdownDTO $mealBreakdown,
        public float $itemTotal
    ) {}

    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'base_subtotal' => round($this->baseSubtotal, 2),
            'extra_guest_fee' => round($this->extraGuestFee, 2),
            'meal_breakdown' => $this->mealBreakdown->toArray(),
            'item_total' => round($this->itemTotal, 2),
        ];
    }
}
