<?php

namespace App\Repositories;

use App\Contracts\Repositories\AmenityRepositoryInterface;
use App\Models\Amenity;
use Illuminate\Pagination\LengthAwarePaginator;

class AmenityRepository implements AmenityRepositoryInterface
{
    public function get(
        array $filters,
        ?string $sort = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        $query = Amenity::query();

        // Search by name
        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        // Sorting
        if ($sort) {
            [$field, $dir] = explode('|', $sort);
            $query->orderBy($field, $dir);
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->paginate($perPage);
    }

    public function getId($id): Amenity
    {
        return Amenity::findOrFail($id);
    }
}
