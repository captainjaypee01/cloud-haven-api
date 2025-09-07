<?php

namespace App\DTO\DayTour;

class DayTourMealLineItemDTO
{
    public function __construct(
        public float $adultPrice,
        public float $childPrice,
        public int $adults,
        public int $children,
        public float $total,
        public bool $applied = true
    ) {}

    public function toArray(): array
    {
        return [
            'adult_price' => round($this->adultPrice, 2),
            'child_price' => round($this->childPrice, 2),
            'adults' => $this->adults,
            'children' => $this->children,
            'total' => round($this->total, 2),
            'applied' => $this->applied,
        ];
    }
}
