<?php

namespace App\Actions\MealPrices;

use App\Contracts\MealPrices\UpdateMealPriceContract;
use App\DTO\MealPrices\UpdateMealPrice;
use App\Models\MealPrice;
use Exception;
use Illuminate\Support\Facades\DB;

final class UpdateMealPriceAction implements UpdateMealPriceContract
{
    public function handle(MealPrice $mealPrice, UpdateMealPrice $dto): MealPrice
    {
        return DB::transaction(function () use ($mealPrice, $dto) {
            if ($dto->category !== $mealPrice->category) {
                if (MealPrice::where('category', $dto->category)->exists()) {
                    throw new Exception('Meal Price category already exists.');
                }
            }

            $updateData = [
                'category'          => $dto->category,
                'price'             => $dto->price,
                'min_age'           => $dto->min_age,
                'max_age'           => $dto->max_age,
            ];
            // Filter out unchanged values
            $changes = array_filter($updateData, fn($val, $key) => $val !== $mealPrice->$key, ARRAY_FILTER_USE_BOTH);
            if (empty($changes)) {
                return $mealPrice; // nothing to update
            }
            $mealPrice->update($changes);
            return $mealPrice->fresh();
        });
    }
}
