<?php

namespace App\Actions\MealPrices;

use App\Contracts\MealPrices\CreateMealPriceContract;
use App\DTO\MealPrices\NewMealPrice;
use App\Models\MealPrice;
use Illuminate\Support\Facades\DB;

final class CreateMealPriceAction implements CreateMealPriceContract
{
    public function handle(NewMealPrice $dto): MealPrice
    {
        return DB::transaction(function () use ($dto) {
            // Create new Promo record
            $mealPrice = MealPrice::create([
                'category'          => $dto->category,
                'price'             => $dto->price,
                'min_age'           => $dto->min_age,
                'max_age'           => $dto->max_age,
            ]);
            return $mealPrice;
        });
    }
}
