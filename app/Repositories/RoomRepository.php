<?php

namespace App\Repositories;

use App\Contracts\Repositories\RoomRepositoryInterface;
use App\Models\BookingRoom;
use App\Models\ReservationLock;
use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RoomRepository implements RoomRepositoryInterface
{
    public function get(
        array $filters,
        ?string $sort = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        $query = Room::query()->with('amenities');

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
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

    public function availableUnits(int $roomId, string $start, string $end): int
    {
        $room = Room::findOrFail($roomId);

        $booked = BookingRoom::where('room_id', $roomId)
            ->whereHas(
                'booking',
                fn($q) =>
                $q->where('status', '!=', 'cancelled')
                    ->where('start_date', '<', $end)
                    ->where('end_date', '>', $start)
            )->sum('quantity');

        $locked = ReservationLock::where('room_id', $roomId)
            ->where('expires_at', '>', now())
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start)
            ->sum('quantity');

        return max($room->quantity - $booked - $locked, 0);
    }

    public function findAvailableRooms(string $start, string $end): mixed
    {
        $rooms = Room::all();
        return $rooms->filter(
            fn($room) =>
            $this->availableUnits($room->id, $start, $end) > 0
        )->values();
    }

    public function getFeaturedRooms(): Collection
    {
        return Room::with('amenities')->where('is_featured', 1)->take(4)->get();
    }
}
