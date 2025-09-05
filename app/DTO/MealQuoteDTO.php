<?php

namespace App\DTO;

class MealQuoteDTO
{
    /**
     * @param MealNightDTO[] $nights
     * @param float $mealSubtotal
     * @param array<string, string> $labels
     */
    public function __construct(
        public array $nights,
        public float $mealSubtotal,
        public array $labels = []
    ) {}

    public function toArray(): array
    {
        return [
            'nights' => array_map(fn($night) => $night->toArray(), $this->nights),
            'meal_subtotal' => round($this->mealSubtotal, 2),
            'labels' => $this->labels,
            'buffet_nights' => $this->buffetNightsCount(),
            'free_breakfast_nights' => $this->freeBreakfastNightsCount(),
        ];
    }

    public function buffetNightsCount(): int
    {
        return count(array_filter($this->nights, fn($night) => $night->type === 'buffet'));
    }

    public function freeBreakfastNightsCount(): int
    {
        return count(array_filter($this->nights, fn($night) => $night->type === 'free_breakfast'));
    }
}
