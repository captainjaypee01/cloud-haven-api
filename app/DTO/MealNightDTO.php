<?php

namespace App\DTO;

use Carbon\Carbon;

class MealNightDTO
{
    public function __construct(
        public Carbon $date,
        public string $type, // 'buffet' or 'free_breakfast'
        public ?float $adultPrice = null,
        public ?float $childPrice = null,
        public int $adults = 0,
        public int $children = 0,
        public float $nightTotal = 0.00,
        public ?float $adultBreakfastPrice = null,
        public ?float $childBreakfastPrice = null,
        public int $extraAdults = 0,
        public int $extraChildren = 0,
        public float $breakfastTotal = 0.00
    ) {}

    public function toArray(): array
    {
        $data = [
            'date' => $this->date->format('Y-m-d'),
            'type' => $this->type,
        ];

        // Include pricing information based on meal type
        if ($this->type === 'buffet' && $this->adultPrice !== null && $this->childPrice !== null) {
            $data['adult_price'] = round($this->adultPrice, 2);
            $data['child_price'] = round($this->childPrice, 2);
        }

        // Always include breakfast pricing for extra guest calculations (if available)
        if ($this->adultBreakfastPrice !== null || $this->childBreakfastPrice !== null) {
            $data['adult_breakfast_price'] = round($this->adultBreakfastPrice ?? 0, 2);
            $data['child_breakfast_price'] = round($this->childBreakfastPrice ?? 0, 2);
        }

        return $data;
    }
}
