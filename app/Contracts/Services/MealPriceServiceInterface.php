<?php

namespace App\Contracts\Services;

use App\Models\MealPrice;
use Illuminate\Pagination\LengthAwarePaginator;

interface MealPriceServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;
    public function show(int $id): MealPrice;
    public function create(array $data): MealPrice;
    public function update(int $id, array $data): MealPrice;
    public function delete(int $id): void;
    public function getMealPrices();

    public function getPriceForCategory(string $category): float;
}
