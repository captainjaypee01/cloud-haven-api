<?php
namespace App\Contracts\MealPrices;

use App\Models\MealPrice;

interface DeleteMealPriceContract { public function handle(MealPrice $mealPrice): void; }
