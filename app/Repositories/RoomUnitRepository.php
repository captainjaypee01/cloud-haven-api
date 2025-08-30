<?php

namespace App\Repositories;

use App\Contracts\Repositories\RoomUnitRepositoryInterface;
use App\Enums\RoomUnitStatusEnum;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RoomUnitRepository implements RoomUnitRepositoryInterface
{
    public function get(
        array $filters,
        ?string $sort = null,
        int $perPage = 10
    ): LengthAwarePaginator {
        $query = RoomUnit::query()->with('room');

        // Filter by room ID
        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        // Filter by status
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Search by unit number
        if (!empty($filters['search'])) {
            $query->where('unit_number', 'like', "%{$filters['search']}%");
        }

        // Sorting
        if ($sort) {
            [$field, $dir] = explode('|', $sort);
            $query->orderBy($field, $dir);
        } else {
            // Default sorting: by room name, then unit number (natural sort)
            $query->join('rooms', 'room_units.room_id', '=', 'rooms.id')
                  ->orderBy('rooms.name')
                  ->orderByRaw('CAST(room_units.unit_number AS UNSIGNED)')
                  ->select('room_units.*');
        }

        return $query->paginate($perPage);
    }

    public function getId(int $id): RoomUnit
    {
        return RoomUnit::with('room')->findOrFail($id);
    }

    public function getUnitsForRoom(int $roomId): Collection
    {
        return RoomUnit::where('room_id', $roomId)
                      ->orderByRaw('CAST(unit_number AS UNSIGNED)')
                      ->get();
    }

    public function getAvailableUnitsForRoom(int $roomId): Collection
    {
        return RoomUnit::where('room_id', $roomId)
                      ->where('status', RoomUnitStatusEnum::AVAILABLE)
                      ->orderByRaw('CAST(unit_number AS UNSIGNED)')
                      ->get();
    }

    public function findAvailableUnitForBooking(int $roomId, string $checkInDate, string $checkOutDate): ?RoomUnit
    {
        return DB::transaction(function () use ($roomId, $checkInDate, $checkOutDate) {
            // Find units that are not blocked or under maintenance
            return RoomUnit::where('room_id', $roomId)
                          ->whereNotIn('status', [RoomUnitStatusEnum::MAINTENANCE, RoomUnitStatusEnum::BLOCKED])
                          ->whereNotExists(function ($query) use ($checkInDate, $checkOutDate) {
                              $query->select(DB::raw(1))
                                   ->from('booking_rooms')
                                   ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
                                   ->whereColumn('booking_rooms.room_unit_id', 'room_units.id')
                                   ->whereIn('bookings.status', ['paid', 'downpayment'])
                                   ->where(function ($q) use ($checkInDate, $checkOutDate) {
                                       $q->where('bookings.check_in_date', '<', $checkOutDate)
                                         ->where('bookings.check_out_date', '>', $checkInDate);
                                   });
                          })
                          ->orderByRaw('CAST(unit_number AS UNSIGNED)')
                          ->lockForUpdate()
                          ->first();
        });
    }

    public function create(array $data): RoomUnit
    {
        return RoomUnit::create($data);
    }

    public function update(RoomUnit $roomUnit, array $data): RoomUnit
    {
        $roomUnit->update($data);
        return $roomUnit->fresh();
    }

    public function delete(RoomUnit $roomUnit): bool
    {
        return $roomUnit->delete();
    }

    public function generateUnits(Room $room, array $unitNumbers, bool $skipExisting = false): Collection
    {
        $createdUnits = [];
        $duplicates = [];
        
        foreach ($unitNumbers as $unitNumber) {
            // Check if unit already exists
            if ($this->unitNumberExists($room->id, $unitNumber)) {
                if ($skipExisting) {
                    continue;
                } else {
                    $duplicates[] = $unitNumber;
                    continue;
                }
            }

            try {
                $unit = $this->create([
                    'room_id' => $room->id,
                    'unit_number' => $unitNumber,
                    'status' => RoomUnitStatusEnum::AVAILABLE,
                ]);
                $createdUnits[] = $unit;
            } catch (\Exception $e) {
                // Handle any other database errors
                if (!$skipExisting) {
                    throw new \Exception("Failed to create unit {$unitNumber}: " . $e->getMessage());
                }
                continue;
            }
        }

        // If we have duplicates and not skipping, throw an error
        if (!empty($duplicates) && !$skipExisting) {
            throw new \InvalidArgumentException(
                "The following room units already exist: " . implode(', ', $duplicates) . 
                ". Enable 'skip existing' to ignore duplicates."
            );
        }

        return new \Illuminate\Database\Eloquent\Collection($createdUnits);
    }

    public function unitNumberExists(int $roomId, string $unitNumber): bool
    {
        return RoomUnit::where('room_id', $roomId)
                      ->where('unit_number', $unitNumber)
                      ->exists();
    }
}
