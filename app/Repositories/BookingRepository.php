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
        $query = Booking::query()->with('bookingRooms.room.images', 'bookingRooms.roomUnit', 'payments', 'cancelledByUser');

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

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter by date range - covers bookings that overlap with the specified date
        if (!empty($filters['date'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('check_in_date', '<=', $filters['date'])
                  ->where('check_out_date', '>', $filters['date']);
            });
        }

        // Filter by date range (from and to)
        if (!empty($filters['date_from'])) {
            $query->where('check_out_date', '>', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('check_in_date', '<=', $filters['date_to']);
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
        return Booking::with('bookingRooms.room', 'bookingRooms.roomUnit', 'payments', 'otherCharges', 'cancelledByUser')->findOrFail($id);
    }

    public function getByReferenceNumber(string $referenceNumber): Booking
    {
        return Booking::with('bookingRooms.room', 'bookingRooms.roomUnit', 'payments', 'otherCharges', 'cancelledByUser')->where('reference_number', $referenceNumber)->firstOrFail();
    }
}
