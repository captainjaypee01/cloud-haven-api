<?php

namespace App\Repositories;

use App\Contracts\Repositories\PromoRepositoryInterface;
use App\Models\Promo;
use Illuminate\Pagination\LengthAwarePaginator;

class PromoRepository implements PromoRepositoryInterface
{
    public function get(array $filters, ?string $sort = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Promo::query();

        // Filter by active status if provided (active/inactive)
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $isActive = $filters['status'] === 'active';
            $query->where('active', $isActive);
        }

        // Search by code
        if (!empty($filters['search'])) {
            $query->where('code', 'like', '%' . $filters['search'] . '%');
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

    public function getId(int $id): Promo
    {
        return Promo::findOrFail($id);
    }

    public function updateActive(Promo $promo, bool $active): Promo
    {
        $promo->update(['active' => $active]);
        return $promo->refresh();
    }

    public function updateActiveBulk(array $ids, bool $active): int
    {
        // Bulk update active status; returns number of records updated
        return Promo::whereIn('id', $ids)->update(['active' => $active]);
    }
}
