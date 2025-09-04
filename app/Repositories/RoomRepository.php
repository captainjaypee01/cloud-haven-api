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
        $availability = $this->getDetailedAvailability($roomId, $startDate, $endDate);
        return $availability['available'];
    }

    public function getDetailedAvailability(int $roomId, string $startDate, string $endDate): array
    {
        $room = Room::findOrFail($roomId);

        // Calculate units booked (from DB bookings) - confirmed bookings only
        $confirmedUnits = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->where('booking_rooms.room_id', $roomId)
            ->whereIn('bookings.status', ['paid', 'downpayment'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('bookings.check_in_date', '<', $endDate)
                    ->where('bookings.check_out_date', '>', $startDate);
            })
            ->count(); // Each booking_room row is one unit

        // Calculate pending bookings with proof of payment uploaded (awaiting verification)
        // These are bookings that have proof uploaded but are still pending (beyond Redis lock period)
        $pendingWithProof = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->where('booking_rooms.room_id', $roomId)
            ->where('bookings.status', 'pending')
            ->where('payments.proof_status', 'pending')
            ->where('bookings.reserved_until', '<', now()) // Only count bookings past the initial hold period
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('bookings.check_in_date', '<', $endDate)
                    ->where('bookings.check_out_date', '>', $startDate);
            })
            ->count();

        Log::info('Pending with proof count: ' . $pendingWithProof . ' for room ' . $roomId . ' and dates ' . $startDate . ' to ' . $endDate);

        // Calculate Redis locked units (initial 2-hour hold period)
        // First, get pending bookings that overlap with our date range
        $pendingBookings = DB::table('bookings')
            ->where('status', 'pending')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('check_in_date', '<', $endDate)
                    ->where('check_out_date', '>', $startDate);
            })
            ->get(['id', 'check_in_date', 'check_out_date']);
        
        $lockedUnits = 0;
        Log::info('Pending bookings for date range: ' . json_encode($pendingBookings->toArray()));
        
        // Get the room slug for comparison (since Redis lock stores room_id as slug)
        $roomSlug = $room->slug;
        Log::info('Looking for room slug: ' . $roomSlug . ' (room ID: ' . $roomId . ')');
        
        foreach ($pendingBookings as $booking) {
            $lock = $this->lockService->get($booking->id);
            if (!$lock) continue;
            
            Log::info('Found lock for booking ' . $booking->id . ': ' . json_encode($lock));
            
            foreach ($lock['rooms'] as $lockedRoom) {
                // Check if this locked room matches our room and date range
                // Note: lockedRoom['room_id'] is actually the room slug, not the database ID
                // Each room entry in Redis represents 1 unit (no quantity field)
                if (
                    $lockedRoom['room_id'] == $roomSlug &&
                    $lock['check_in_date'] < $endDate &&
                    $lock['check_out_date'] > $startDate
                ) {
                    Log::info('Locked unit found - Room Slug: ' . $lockedRoom['room_id'] . ', Dates: ' . $lock['check_in_date'] . ' to ' . $lock['check_out_date'] . ', Count: 1 unit');
                    $lockedUnits += 1; // Each room entry = 1 unit
                }
            }
        }

        // Calculate units in maintenance or blocked status
        $unavailableUnits = DB::table('room_units')
            ->where('room_id', $roomId)
            ->whereIn('status', ['maintenance', 'blocked'])
            ->count();

        // Combine all pending units (locked + pending with proof)
        $totalPending = $lockedUnits + $pendingWithProof;
        
        // Total unavailable = confirmed + pending + maintenance
        $totalUnavailable = $confirmedUnits + $totalPending + $unavailableUnits;
        $available = max(0, $room->quantity - $totalUnavailable);

        Log::info('Availability breakdown for room ' . $roomId . ' (' . $room->name . '):');
        Log::info('- Total units: ' . $room->quantity);
        Log::info('- Confirmed: ' . $confirmedUnits);
        Log::info('- Pending (locked + proof): ' . $totalPending . ' (locked: ' . $lockedUnits . ', proof: ' . $pendingWithProof . ')');
        Log::info('- Maintenance: ' . $unavailableUnits);
        Log::info('- Total unavailable: ' . $totalUnavailable);
        Log::info('- Available: ' . $available);

        return [
            'available' => $available,
            'pending' => $totalPending,
            'confirmed' => $confirmedUnits,
            'maintenance' => $unavailableUnits,
            'total_units' => $room->quantity,
            'room_id' => $roomId,
            'room_name' => $room->name
        ];
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
