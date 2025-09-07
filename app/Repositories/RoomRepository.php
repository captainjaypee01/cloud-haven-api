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
            })
            ->count();

        Log::info('Pending with proof count: ' . $pendingWithProof . ' for room ' . $roomId . ' and dates ' . $startDate . ' to ' . $endDate);

        // Calculate Redis locked units (initial 2-hour hold period)
        // First, get pending bookings that overlap with our date range
        $pendingBookings = DB::table('bookings')
            ->where('status', 'pending')
            ->where(function ($q) use ($startDate, $endDate) {
                // Handle both overnight and day tour bookings
                $q->where(function ($subQ) use ($startDate, $endDate) {
                    // Overnight bookings: check_in < endDate AND check_out > startDate
                    $subQ->where('check_in_date', '<', $endDate)
                        ->where('check_out_date', '>', $startDate);
                })->orWhere(function ($subQ) use ($startDate) {
                    // Day tour bookings: check_in_date = startDate (same day booking)
                    $subQ->where('booking_type', 'day_tour')
                        ->where('check_in_date', $startDate);
                });
            })
            ->get(['id', 'check_in_date', 'check_out_date', 'booking_type']);
        
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
                $isRoomMatch = $lockedRoom['room_id'] == $roomSlug;
                $isDateMatch = false;
                
                // Handle date matching based on booking type
                if ($booking->booking_type === 'day_tour') {
                    // For Day Tour: check if the locked date matches our start date
                    $isDateMatch = $lock['check_in_date'] == $startDate;
                } else {
                    // For Overnight: check if dates overlap
                    $isDateMatch = $lock['check_in_date'] < $endDate && $lock['check_out_date'] > $startDate;
                }
                
                if ($isRoomMatch && $isDateMatch) {
                    Log::info('Locked unit found - Room Slug: ' . $lockedRoom['room_id'] . ', Dates: ' . $lock['check_in_date'] . ' to ' . $lock['check_out_date'] . ', Type: ' . ($booking->booking_type ?? 'overnight') . ', Count: 1 unit');
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
                $room->available_count = $this->getAvailableUnits($room->id, $start, $end);
                return $room;
            });
    }

    public function getDayTourRoomsWithAvailability(\Carbon\Carbon $date): Collection
    {
        $startDate = $date->format('Y-m-d');
        $endDate = $date->copy()->addDay()->format('Y-m-d');
        
        return Room::with(['amenities', 'roomUnits'])
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
