<?php

namespace App\Repositories;

use App\Contracts\Repositories\MealPriceRepositoryInterface;
use App\Models\MealPrice;
use Illuminate\Pagination\LengthAwarePaginator;

class MealPriceRepository implements MealPriceRepositoryInterface
{
    public function get(array $filters, ?string $sort = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = MealPrice::query();

        // Filter by active status if provided (active/inactive)
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        // Search by code
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->orWhere('category', 'like', "%{$filters['search']}%")
                    ->orWhere('min_age', 'like', "%{$filters['search']}%")
                    ->orWhere('max_age', 'like', "%{$filters['search']}%")
                    ->orWhere('price', 'like', "%{$filters['search']}%");
            });
        }

        // Sorting
        if ($sort) {
            [$field, $dir] = explode('|', $sort);
            $query->orderBy($field, $dir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function getId(int $id): MealPrice
    {
        return MealPrice::findOrFail($id);
    }
}
