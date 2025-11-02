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
            // Find units that are available for the given date range
            return RoomUnit::where('room_id', $roomId)
                          // Exclude units that have conflicting bookings (paid, downpayment, and pending with assigned units)
                          ->whereNotExists(function ($query) use ($checkInDate, $checkOutDate) {
                              $query->select(DB::raw(1))
                                   ->from('booking_rooms')
                                   ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
                                   ->whereColumn('booking_rooms.room_unit_id', 'room_units.id')
                                   ->where(function ($statusQuery) {
                                       $statusQuery->whereIn('bookings.status', ['paid', 'downpayment'])
                                                  ->orWhere(function ($pendingQuery) {
                                                      // Include pending bookings that have room units assigned
                                                      $pendingQuery->where('bookings.status', 'pending')
                                                                  ->whereNotNull('booking_rooms.room_unit_id');
                                                  });
                                   })
                                   ->where(function ($q) use ($checkInDate, $checkOutDate) {
                                       // Handle both overnight and day tour bookings
                                       $q->where(function ($overnightQuery) use ($checkInDate, $checkOutDate) {
                                           // Overnight bookings: check_in < endDate AND check_out > startDate
                                           $overnightQuery->where('bookings.check_in_date', '<', $checkOutDate)
                                                         ->where('bookings.check_out_date', '>', $checkInDate);
                                       })->orWhere(function ($dayTourQuery) use ($checkInDate) {
                                           // Day tour bookings: check_in_date = startDate (same day booking)
                                           $dayTourQuery->where('bookings.booking_type', 'day_tour')
                                                       ->where('bookings.check_in_date', $checkInDate);
                                       });
                                   });
                          })
                          // Exclude units that are in maintenance during the booking period
                          ->where(function ($query) use ($checkInDate, $checkOutDate) {
                              $query->where(function ($subQuery) use ($checkInDate, $checkOutDate) {
                                  // Allow units that are NOT in maintenance status
                                  $subQuery->where('status', '!=', RoomUnitStatusEnum::MAINTENANCE);
                              })->orWhere(function ($subQuery) use ($checkInDate, $checkOutDate) {
                                  // OR allow maintenance units where dates don't overlap
                                  $subQuery->where('status', RoomUnitStatusEnum::MAINTENANCE)
                                      ->where(function ($dateQuery) use ($checkInDate, $checkOutDate) {
                                          $dateQuery->whereNull('maintenance_start_at')
                                              ->orWhereNull('maintenance_end_at')
                                              ->orWhere('maintenance_end_at', '<', $checkInDate)
                                              ->orWhere('maintenance_start_at', '>', $checkOutDate);
                                      });
                              });
                          })
                          // Exclude units that have active blocked dates during the booking period
                          // Only exclude blocked dates that are active AND not expired (expiry_date >= today)
                          // Also handle NULL expiry_date (treat as expired)
                          ->whereNotExists(function ($query) use ($checkInDate, $checkOutDate) {
                              $query->select(DB::raw(1))
                                   ->from('room_unit_blocked_dates')
                                   ->whereColumn('room_unit_blocked_dates.room_unit_id', 'room_units.id')
                                   ->where('room_unit_blocked_dates.active', true)
                                   ->whereNotNull('room_unit_blocked_dates.expiry_date') // Exclude NULL expiry dates
                                   ->where('room_unit_blocked_dates.expiry_date', '>=', now()->toDateString())
                                   ->where('room_unit_blocked_dates.start_date', '<=', $checkOutDate)
                                   ->where('room_unit_blocked_dates.end_date', '>=', $checkInDate);
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
