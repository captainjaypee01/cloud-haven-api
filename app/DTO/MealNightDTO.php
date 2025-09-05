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
        public float $nightTotal = 0.00
    ) {}

    public function toArray(): array
    {
        $data = [
            'date' => $this->date->format('Y-m-d'),
            'type' => $this->type,
            'adults' => $this->adults,
            'children' => $this->children,
            'night_total' => round($this->nightTotal, 2),
        ];

        if ($this->type === 'buffet') {
            $data['adult_price'] = round($this->adultPrice ?? 0, 2);
            $data['child_price'] = round($this->childPrice ?? 0, 2);
        }

        return $data;
    }
}
