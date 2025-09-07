<?php

namespace App\Repositories;

use App\Contracts\Repositories\DayTourPricingRepositoryInterface;
use App\Models\DayTourPricing;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class DayTourPricingRepository implements DayTourPricingRepositoryInterface
{
    public function get(array $filters, ?string $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = DayTourPricing::query();

        // Search by name
        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        // Filter by active status
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Sort
        if ($sort) {
            [$field, $direction] = explode('|', $sort);
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('effective_from', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): ?DayTourPricing
    {
        return DayTourPricing::find($id);
    }

    public function create(array $data): DayTourPricing
    {
        return DayTourPricing::create($data);
    }

    public function update(DayTourPricing $pricing, array $data): DayTourPricing
    {
        $pricing->update($data);
        return $pricing->fresh();
    }

    public function delete(DayTourPricing $pricing): bool
    {
        return $pricing->delete();
    }

    public function toggleStatus(DayTourPricing $pricing): DayTourPricing
    {
        $pricing->update(['is_active' => !$pricing->is_active]);
        return $pricing->fresh();
    }

    public function getActivePricingForDate(string $date): ?DayTourPricing
    {
        return DayTourPricing::where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $date);
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    public function getCurrentActivePricing(): ?DayTourPricing
    {
        return $this->getActivePricingForDate(Carbon::now()->format('Y-m-d'));
    }
}
