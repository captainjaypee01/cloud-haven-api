<?php

namespace App\Actions\MealPrices;

use App\Contracts\MealPrices\DeleteMealPriceContract;
use App\Models\MealPrice;
use Illuminate\Support\Facades\DB;

final class DeleteMealPriceAction implements DeleteMealPriceContract
{
    public function handle(MealPrice $mealPrice): void
    {
        DB::transaction(function () use ($mealPrice) {
            $mealPrice->delete();
        });
    }
}
