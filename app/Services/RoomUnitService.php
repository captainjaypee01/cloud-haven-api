<?php

namespace App\Services;

use App\Contracts\Repositories\RoomUnitRepositoryInterface;
use App\DTO\RoomUnits\GenerateUnitsData;
use App\Enums\RoomUnitStatusEnum;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomUnitService
{
    public function __construct(
        private readonly RoomUnitRepositoryInterface $roomUnitRepository
    ) {}

    /**
     * Get room units with filtering and pagination.
     */
    public function getRoomUnits(array $filters, ?string $sort = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->roomUnitRepository->get($filters, $sort, $perPage);
    }

    /**
     * Get a room unit by ID.
     */
    public function getRoomUnit(int $id): RoomUnit
    {
        return $this->roomUnitRepository->getId($id);
    }

    /**
     * Get all units for a specific room type.
     */
    public function getUnitsForRoom(int $roomId): Collection
    {
        return $this->roomUnitRepository->getUnitsForRoom($roomId);
    }

    /**
     * Generate room units in bulk.
     * 
     * Status definitions:
     * - Available: Unit is ready for booking
     * - Booked/Assigned: Unit is assigned to a confirmed booking
     * - Under Maintenance: Unit is temporarily unavailable due to maintenance
     * - Blocked: Unit is manually blocked from bookings
     */
    public function generateUnits(Room $room, GenerateUnitsData $data): array
    {
        $unitNumbers = [];

        // Handle ranges
        if (!empty($data->ranges)) {
            foreach ($data->ranges as $range) {
                $prefix = $range['prefix'] ?? '';
                $start = (int) $range['start'];
                $end = (int) $range['end'];

                if ($start > $end) {
                    throw new \InvalidArgumentException("Range start ({$start}) cannot be greater than end ({$end})");
                }

                for ($i = $start; $i <= $end; $i++) {
                    $unitNumbers[] = $prefix . str_pad($i, strlen($range['start']), '0', STR_PAD_LEFT);
                }
            }
        }

        // Handle explicit numbers
        if (!empty($data->numbers)) {
            $unitNumbers = array_merge($unitNumbers, $data->numbers);
        }

        // Remove duplicates
        $unitNumbers = array_unique($unitNumbers);

        if (empty($unitNumbers)) {
            throw new \InvalidArgumentException("No unit numbers provided");
        }

        // Check if total units (existing + new) would exceed room quantity
        $existingUnitsCount = $this->roomUnitRepository->getUnitsForRoom($room->id)->count();
        $newUnitsCount = count($unitNumbers);
        $totalUnitsAfterCreation = $existingUnitsCount + $newUnitsCount;
        
        if ($totalUnitsAfterCreation > $room->quantity) {
            $remainingCapacity = max(0, $room->quantity - $existingUnitsCount);
            
            if ($existingUnitsCount === 0) {
                throw new \InvalidArgumentException(
                    "Cannot create {$newUnitsCount} units. Room '{$room->name}' has a maximum capacity of {$room->quantity} units. " .
                    "You can only create {$remainingCapacity} units."
                );
            } else {
                throw new \InvalidArgumentException(
                    "Cannot create {$newUnitsCount} units. Room '{$room->name}' already has {$existingUnitsCount} units and has a maximum capacity of {$room->quantity} units. " .
                    "You can only create {$remainingCapacity} more units."
                );
            }
        }

        try {
            $created = $this->roomUnitRepository->generateUnits($room, $unitNumbers, $data->skip_existing ?? false);
            
            $skipped = array_diff($unitNumbers, $created->pluck('unit_number')->toArray());

            return [
                'created' => $created,
                'skipped' => $skipped,
                'total_requested' => count($unitNumbers),
                'total_created' => $created->count(),
                'total_skipped' => count($skipped),
            ];
        } catch (\InvalidArgumentException $e) {
            // Handle duplicate error gracefully
            throw $e;
        }
    }

    /**
     * Update a room unit's status and notes.
     */
    public function updateRoomUnit(RoomUnit $roomUnit, array $data): RoomUnit
    {
        $allowedFields = ['status', 'notes'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        return $this->roomUnitRepository->update($roomUnit, $updateData);
    }

    /**
     * Delete a room unit if it's not currently assigned to any bookings.
     */
    public function deleteRoomUnit(RoomUnit $roomUnit): bool
    {
        // Check if the unit is assigned to any bookings
        if ($roomUnit->bookingRooms()->exists()) {
            throw new \InvalidArgumentException("Cannot delete room unit {$roomUnit->unit_number} as it's assigned to existing bookings");
        }

        return $this->roomUnitRepository->delete($roomUnit);
    }

    /**
     * Assign a room unit to a booking room.
     * This is the core logic for room unit assignment during booking confirmation.
     * The unit status remains unchanged - availability is tracked by booking dates.
     */
    public function assignUnitToBooking(int $roomId, string $checkInDate, string $checkOutDate): ?RoomUnit
    {
        return DB::transaction(function () use ($roomId, $checkInDate, $checkOutDate) {
            // Find an available unit using the repository's locking mechanism
            $unit = $this->roomUnitRepository->findAvailableUnitForBooking($roomId, $checkInDate, $checkOutDate);

            if (!$unit) {
                Log::warning("No available units found for room {$roomId} from {$checkInDate} to {$checkOutDate}");
                return null;
            }

            // Don't change unit status - availability is tracked by booking dates
            Log::info("Assigned unit {$unit->unit_number} to booking for room {$roomId} from {$checkInDate} to {$checkOutDate}");

            return $unit;
        });
    }

    /**
     * Release a room unit when a booking is cancelled or completed.
     * Note: With date-based availability, this method is mainly for logging.
     */
    public function releaseUnit(RoomUnit $roomUnit): void
    {
        Log::info("Released unit {$roomUnit->unit_number} - availability now tracked by booking dates");
    }

    /**
     * Get room units that are currently occupied and past their checkout date.
     * This can be used for cleanup tasks.
     */
    public function getUnitsNeedingCheckout(): Collection
    {
        return RoomUnit::where('status', RoomUnitStatusEnum::OCCUPIED)
            ->whereHas('bookingRooms.booking', function ($query) {
                $query->where('check_out_date', '<', now()->toDateString())
                      ->whereIn('status', ['paid', 'downpayment']);
            })
            ->with(['bookingRooms.booking'])
            ->get();
    }

    /**
     * Auto-release units that are past their checkout date.
     */
    public function autoReleaseCheckedOutUnits(): int
    {
        $units = $this->getUnitsNeedingCheckout();
        $released = 0;

        foreach ($units as $unit) {
            $this->releaseUnit($unit);
            $released++;
        }

        if ($released > 0) {
            Log::info("Auto-released {$released} units that were past checkout");
        }

        return $released;
    }

    /**
     * Get availability statistics for a room type.
     * "Occupied" represents the count of current or upcoming bookings (check-out date >= today).
     */
    public function getRoomAvailabilityStats(int $roomId): array
    {
        $units = $this->roomUnitRepository->getUnitsForRoom($roomId);
        
        $stats = [
            'total' => $units->count(),
            'available' => 0,
            'occupied' => 0,
            'maintenance' => 0,
            'blocked' => 0,
        ];

        $today = now()->toDateString();

        // Count units by their manual status
        foreach ($units as $unit) {
            $status = $unit->status->value;
            
            if ($status === 'maintenance' || $status === 'blocked') {
                $stats[$status]++;
            } else {
                $stats['available']++;
            }
        }

        // Count active/upcoming bookings (this is the "occupied" count)
        $activeBookingsCount = \App\Models\BookingRoom::where('room_id', $roomId)
            ->whereHas('booking', function ($query) use ($today) {
                $query->whereIn('status', ['paid', 'downpayment'])
                      ->where('check_out_date', '>=', $today); // Current or future bookings
            })
            ->count();

        $stats['occupied'] = $activeBookingsCount;

        return $stats;
    }
}
