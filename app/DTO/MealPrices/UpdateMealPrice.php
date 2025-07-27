<?php

namespace App\DTO\MealPrices;

use Spatie\LaravelData\Data;

class UpdateMealPrice extends Data
{
    public function __construct(
        public string  $category,
        public float   $price,        // 'fixed' or 'percentage'
        public ?int    $min_age = null,
        public ?int    $max_age = null,           // nullable timestamp string
    ) {}
}
