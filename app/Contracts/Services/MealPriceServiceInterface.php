<?php

namespace App\Contracts\Services;

interface MealPriceServiceInterface
{
    public function getMealPrices();

    public function getPriceForCategory(string $category): float;
}
