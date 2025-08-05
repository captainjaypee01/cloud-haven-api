<?php

namespace App\Repositories;

use App\Contracts\Repositories\RoomRepositoryInterface;
use App\Contracts\Services\BookingLockServiceInterface;
use App\Enums\RoomStatusEnum;
use App\Models\BookingRoom;
use App\Models\ReservationLock;
use App\Models\Room;
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

    public function getAvailableUnits(int $roomId, string $startDate, string $endDate): int
    {
        $room = Room::findOrFail($roomId);

        // Calculate units booked (from DB bookings)
        $bookedUnits = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->where('booking_rooms.room_id', $roomId)
            ->whereIn('bookings.status', ['paid', 'downpayment'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('bookings.check_in_date', '<', $endDate)
                    ->where('bookings.check_out_date', '>', $startDate);
            })
            ->count(); // Each booking_room row is one unit

        $pendingBookingIds = DB::table('bookings')
            ->where('status', 'pending')
            ->pluck('id');
        $lockedUnits = 0;
        foreach ($pendingBookingIds as $bookingId) {
            $lock = $this->lockService->get($bookingId);
            if (!$lock) continue;
            foreach ($lock['rooms'] as $lockedRoom) {
                if (
                    $lockedRoom['room_id'] == $roomId &&
                    $lock['check_in_date'] < $endDate &&
                    $lock['check_out_date'] > $startDate
                ) {
                    $lockedUnits += $lockedRoom['quantity'];
                }
            }
        }

        $available = $room->quantity - $bookedUnits - $lockedUnits;

        return max(0, $available);
    }

    public function findAvailableRooms(string $start, string $end): mixed
    {
        $rooms = Room::all();
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
        ])->where('is_featured', 1)->where('status', RoomStatusEnum::AVAILABLE)->take(4)->get();
    }

    public function listRoomsWithAvailability(string $start, string $end)
    {
        return Room::with('amenities')->get()->map(function ($room) use ($start, $end) {
            $room->available_count = $this->getAvailableUnits($room->id, $start, $end);
            return $room;
        });
    }
}
