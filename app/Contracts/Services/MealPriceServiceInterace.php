<?php

namespace App\Contracts\Services;

interface MealPriceServiceInterace
{
    public function getMealPrices();

    public function getPriceForCategory(string $category): float;
}
