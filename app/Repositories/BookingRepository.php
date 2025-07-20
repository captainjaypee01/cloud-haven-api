<?php

namespace App\Repositories;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Models\Booking;
use Illuminate\Pagination\LengthAwarePaginator;

class BookingRepository implements BookingRepositoryInterface
{

    public function get(
        array $filters,
        ?string $sort = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        $query = Booking::query()->with('bookingRooms.room', 'payments');

        // Filter by status
        if (!empty($filters['status'])) {
            if ($filters['status'] != 'all')
                $query->where('status', $filters['status']);
        }

        // Search by name, reference number
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->orWhere('guest_name', 'like', "%{$filters['search']}%")
                    ->orWhere('guest_email', 'like', "%{$filters['search']}%")
                    ->orWhere('reference_number', 'like', "%{$filters['search']}%");
            });
        }

        // Sorting
        if ($sort) {
            [$field, $dir] = explode('|', $sort);
            if ($field == "price") $field = "final_price";
            $query->orderBy($field, $dir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }
    
    public function getId($id): Booking
    {
        return Booking::with('bookingRooms.room', 'payments', 'otherCharges')->findOrFail($id);
    }

    public function getByReferenceNumber(string $referenceNumber): Booking
    {
        return Booking::with('bookingRooms.room', 'payments', 'otherCharges')->where('reference_number', $referenceNumber)->firstOrFail();
    }
}
