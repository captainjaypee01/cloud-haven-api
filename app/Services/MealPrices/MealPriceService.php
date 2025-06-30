<?php
namespace App\Services\MealPrices;

use App\Contracts\Services\MealPriceServiceInterface;
use App\Models\MealPrice;

class MealPriceService implements MealPriceServiceInterface
{
    public function getMealPrices()
    {
        return MealPrice::select("category", "price")->get()->keyBy('category');
    }

    public function getPriceForCategory(string $category): float
    {
        $mealPrices = $this->getMealPrices();
        return $mealPrices[$category]->price ?? 0;
    }
}
