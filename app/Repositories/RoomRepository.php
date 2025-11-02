<?php

namespace App\Repositories;

use App\Contracts\Repositories\RoomRepositoryInterface;
use App\Contracts\Services\BookingLockServiceInterface;
use App\Enums\RoomStatusEnum;
use App\Models\BookingRoom;
use App\Models\ReservationLock;
use App\Models\Room;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomRepository implements RoomRepositoryInterface
{

    public function __construct(private readonly BookingLockServiceInterface $lockService) {}
    public function get(
        array $filters,
        ?string $sort = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        $query = Room::query()->with([
            'amenities',
            'images' => function ($q) {
                $q->orderBy('room_image.order');
            }
        ]);

        // Filter by status
        if (!empty($filters['status'])) {
            if ($filters['status'] != 'all')
                $query->where('status', RoomStatusEnum::fromLabel($filters['status'])->value);
        }

        // Filter by room type
        if (!empty($filters['room_type'])) {
            if ($filters['room_type'] != 'all')
                $query->where('room_type', $filters['room_type']);
        }

        // Filter by featured
        if (!empty($filters['featured'])) {
            $query->where('is_featured', $filters['featured']);
        }

        // Search by name
        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        // Sorting
        if ($sort) {
            [$field, $dir] = explode('|', $sort);
            if ($field == "price") $field = "price_per_night";
            $query->orderBy($field, $dir);
        } else {
            $query->orderBy('id', 'asc');
        }

        return $query->paginate($perPage);
    }

    public function getId($id): Room
    {
        return Room::findOrFail($id);
    }

    public function getBySlug(string $slug): Room
    {
        return Room::with('amenities')->where('slug', $slug)->firstOrFail();
    }

    public function getAvailableUnits(int $roomId, string $startDate, string $endDate, ?int $excludeBookingId = null): int
    {
        $availability = $this->getDetailedAvailability($roomId, $startDate, $endDate, $excludeBookingId);
        return $availability['available'];
    }

    public function getDetailedAvailability(int $roomId, string $startDate, string $endDate, ?int $excludeBookingId = null): array
    {
        $room = Room::findOrFail($roomId);

        // 1. Count confirmed units - bookings with paid/downpayment status
        $confirmedUnitsQuery = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->where('booking_rooms.room_id', $roomId)
            ->whereIn('bookings.status', ['paid', 'downpayment'])
            ->where(function ($q) use ($startDate, $endDate) {
                // Handle both overnight and day tour bookings
                $q->where(function ($subQ) use ($startDate, $endDate) {
                    // Overnight bookings: check_in < endDate AND check_out > startDate
                    $subQ->where('bookings.check_in_date', '<', $endDate)
                        ->where('bookings.check_out_date', '>', $startDate);
                })->orWhere(function ($subQ) use ($startDate) {
                    // Day tour bookings: check_in_date = startDate (same day booking)
                    $subQ->where('bookings.booking_type', 'day_tour')
                        ->where('bookings.check_in_date', $startDate);
                });
            });
        
        // Exclude specific booking if provided
        if ($excludeBookingId) {
            $confirmedUnitsQuery->where('bookings.id', '!=', $excludeBookingId);
        }
        
        // Count distinct room units (not booking_rooms records) to avoid counting the same unit twice
        $confirmedUnits = $confirmedUnitsQuery->distinct()->count('booking_rooms.room_unit_id');

        // 2. Count pending units Part 1 - bookings with payment records (proof uploaded)
        // Status doesn't matter as long as there's a payment record
        $pendingWithPaymentQuery = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->where('booking_rooms.room_id', $roomId)
            ->where('bookings.status', 'pending')
            ->where(function ($q) use ($startDate, $endDate) {
                // Handle both overnight and day tour bookings
                $q->where(function ($subQ) use ($startDate, $endDate) {
                    // Overnight bookings: check_in < endDate AND check_out > startDate
                    $subQ->where('bookings.check_in_date', '<', $endDate)
                        ->where('bookings.check_out_date', '>', $startDate);
                })->orWhere(function ($subQ) use ($startDate) {
                    // Day tour bookings: check_in_date = startDate (same day booking)
                    $subQ->where('bookings.booking_type', 'day_tour')
                        ->where('bookings.check_in_date', $startDate);
                });
            });
        
        // Exclude specific booking if provided
        if ($excludeBookingId) {
            $pendingWithPaymentQuery->where('bookings.id', '!=', $excludeBookingId);
        }
        
        // Count distinct room units (not booking_rooms records) to avoid counting the same unit twice
        $pendingWithPayment = $pendingWithPaymentQuery->distinct()->count('booking_rooms.room_unit_id');

        // 3. Count pending units Part 2 - bookings without payment records (within reserved_until period)
        $pendingWithoutPaymentQuery = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->leftJoin('payments', 'bookings.id', '=', 'payments.booking_id')
            ->where('booking_rooms.room_id', $roomId)
            ->where('bookings.status', 'pending')
            ->whereNull('payments.booking_id') // No payment record exists
            ->where('bookings.reserved_until', '>', now()) // Still within reserved period
            ->where(function ($q) use ($startDate, $endDate) {
                // Handle both overnight and day tour bookings
                $q->where(function ($subQ) use ($startDate, $endDate) {
                    // Overnight bookings: check_in < endDate AND check_out > startDate
                    $subQ->where('bookings.check_in_date', '<', $endDate)
                        ->where('bookings.check_out_date', '>', $startDate);
                })->orWhere(function ($subQ) use ($startDate) {
                    // Day tour bookings: check_in_date = startDate (same day booking)
                    $subQ->where('bookings.booking_type', 'day_tour')
                        ->where('bookings.check_in_date', $startDate);
                });
            });
        
        // Exclude specific booking if provided
        if ($excludeBookingId) {
            $pendingWithoutPaymentQuery->where('bookings.id', '!=', $excludeBookingId);
        }
        
        // Count distinct room units (not booking_rooms records) to avoid counting the same unit twice
        $pendingWithoutPayment = $pendingWithoutPaymentQuery->distinct()->count('booking_rooms.room_unit_id');

        // 4. Count unavailable units from room_units table (maintenance only)
        // Check units that are in maintenance status AND their date ranges overlap with our search dates
        $maintenanceUnits = DB::table('room_units')
            ->where('room_id', $roomId)
            ->where('status', 'maintenance')
            ->whereNotNull('maintenance_start_at')
            ->whereNotNull('maintenance_end_at')
            ->where('maintenance_start_at', '<=', $endDate)
            ->where('maintenance_end_at', '>=', $startDate)
            ->count();

        // 5. Count units with active blocked dates that overlap with our search dates
        // Only count blocked dates that are active AND not expired (expiry_date >= today)
        // Also handle NULL expiry_date (treat as expired)
        $blockedUnits = DB::table('room_units')
            ->join('room_unit_blocked_dates', 'room_units.id', '=', 'room_unit_blocked_dates.room_unit_id')
            ->where('room_units.room_id', $roomId)
            ->where('room_unit_blocked_dates.active', 1)
            ->whereNotNull('room_unit_blocked_dates.expiry_date') // Exclude NULL expiry dates
            ->where('room_unit_blocked_dates.expiry_date', '>=', now()->toDateString())
            ->where('room_unit_blocked_dates.start_date', '<=', $endDate)
            ->where('room_unit_blocked_dates.end_date', '>=', $startDate)
            ->distinct()
            ->count('room_units.id');

        // Calculate totals
        $totalPending = $pendingWithPayment + $pendingWithoutPayment;
        $totalUnavailable = $confirmedUnits + $totalPending + $maintenanceUnits + $blockedUnits;
        $available = max(0, $room->quantity - $totalUnavailable);


        return [
            'available' => $available,
            'pending' => $totalPending,
            'confirmed' => $confirmedUnits,
            'maintenance' => $maintenanceUnits,
            'blocked' => $blockedUnits,
            'total_units' => $room->quantity,
            'room_id' => $roomId,
            'room_name' => $room->name
        ];
    }

    public function findAvailableRooms(string $start, string $end): mixed
    {
        $rooms = Room::where('room_type', 'overnight')->get();
        return $rooms->filter(
            fn($room) =>
            $this->getAvailableUnits($room->id, $start, $end) > 0
        )->values();
    }

    public function getFeaturedRooms(): Collection
    {
        return Room::with([
            'amenities',
            'images' => fn($q) => $q->orderBy('room_image.order')
        ])->where('room_type', 'overnight')
          ->where('is_featured', 1)
          ->where('status', RoomStatusEnum::AVAILABLE)
          ->take(4)
          ->get();
    }

    public function listRoomsWithAvailability(string $start, string $end)
    {
        return Room::with('amenities')
            ->where('room_type', 'overnight')
            ->get()
            ->map(function ($room) use ($start, $end) {
                // Get detailed availability information instead of just available count
                $availability = $this->getDetailedAvailability($room->id, $start, $end);
                
                // Set the availability data on the room model
                $room->available_count = $availability['available'];
                $room->pending_count = $availability['pending'];
                $room->confirmed_count = $availability['confirmed'];
                $room->maintenance_count = $availability['maintenance'];
                $room->blocked_count = $availability['blocked'];
                $room->total_units = $availability['total_units'];
                
                return $room;
            });
    }

    public function getDayTourRoomsWithAvailability(\Carbon\Carbon $date): Collection
    {
        $startDate = $date->format('Y-m-d');
        $endDate = $date->copy()->addDay()->format('Y-m-d');
        
        return Room::with(['amenities', 'images', 'roomUnits'])
            ->where('room_type', 'day_tour')
            ->where('status', RoomStatusEnum::AVAILABLE)
            ->get()
            ->map(function ($room) use ($startDate, $endDate) {
                $availability = $this->getDetailedAvailability($room->id, $startDate, $endDate);
                $room->available_units = $availability['available'];
                return $room;
            })
            ->filter(fn($room) => $room->available_units > 0);
    }

    public function findByIds(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }
        
        return Room::whereIn('id', $ids)->get();
    }
}
