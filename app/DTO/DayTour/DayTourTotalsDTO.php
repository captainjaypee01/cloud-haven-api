<?php

namespace App\DTO\DayTour;

class DayTourTotalsDTO
{
    public function __construct(
        public float $roomsSubtotal,
        public float $mealsSubtotal,
        public float $grandTotal
    ) {}

    public function toArray(): array
    {
        return [
            'rooms_subtotal' => round($this->roomsSubtotal, 2),
            'meals_subtotal' => round($this->mealsSubtotal, 2),
            'grand_total' => round($this->grandTotal, 2),
        ];
    }
}
