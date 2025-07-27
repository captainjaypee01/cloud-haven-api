<?php
namespace App\Contracts\MealPrices;

use App\DTO\MealPrices\UpdateMealPrice;
use App\Models\MealPrice;

interface UpdateMealPriceContract { public function handle(MealPrice $mealPrice, UpdateMealPrice $dto): MealPrice; }
