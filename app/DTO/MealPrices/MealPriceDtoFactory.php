<?php

namespace App\DTO\MealPrices;

use App\DTO\MealPrices\NewMealPrice;
use App\DTO\MealPrices\UpdateMealPrice;

class MealPriceDtoFactory
{
    public function newMealPrice(array $data): NewMealPrice
    {
        return NewMealPrice::from($data);
    }

    public function updateMealPrice(array $data): UpdateMealPrice
    {
        return UpdateMealPrice::from($data);
    }
}
