<?php
namespace App\Contracts\MealPrices;

use App\DTO\MealPrices\NewMealPrice;
use App\Models\MealPrice;

interface CreateMealPriceContract { public function handle(NewMealPrice $dto): MealPrice; }
