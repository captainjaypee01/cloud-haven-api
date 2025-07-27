<?php

namespace App\Contracts\Repositories;

use App\Models\MealPrice;

interface MealPriceRepositoryInterface extends RootRepositoryInterface
{
    public function getId(int $id): MealPrice;
}
