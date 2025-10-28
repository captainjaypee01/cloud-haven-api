<?php

namespace App\Services;

use App\Models\RoomUnit;
use App\Models\RoomUnitBlockedDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomUnitBlockedDateService
{
    /**
     * Get blocked dates for a specific room unit with pagination.
     */
    public function getBlockedDatesForUnit(int $roomUnitId, array $filters = [], ?string $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = RoomUnitBlockedDate::where('room_unit_id', $roomUnitId);

        // Apply filters
        if (!empty($filters['active'])) {
            $query->where('active', $filters['active'] === 'true');
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('notes', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['date_from'])) {
            $query->where('start_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('end_date', '<=', $filters['date_to']);
        }

        // Apply sorting
        if ($sort) {
            [$field, $direction] = explode('|', $sort);
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('start_date', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get all blocked dates across all room units with pagination.
     */
    public function getAllBlockedDates(array $filters = [], ?string $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = RoomUnitBlockedDate::with('roomUnit.room');

        // Apply filters
        if (!empty($filters['room_unit_id'])) {
            $query->where('room_unit_id', $filters['room_unit_id']);
        }

        if (!empty($filters['room_id'])) {
            $query->whereHas('roomUnit', function ($q) use ($filters) {
                $q->where('room_id', $filters['room_id']);
            });
        }

        if (!empty($filters['active'])) {
            $query->where('active', $filters['active'] === 'true');
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('notes', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('roomUnit.room', function ($roomQuery) use ($filters) {
                      $roomQuery->where('name', 'like', '%' . $filters['search'] . '%');
                  });
            });
        }

        if (!empty($filters['date_from'])) {
            $query->where('start_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('end_date', '<=', $filters['date_to']);
        }

        // Apply sorting
        if ($sort) {
            [$field, $direction] = explode('|', $sort);
            $query->orderBy($field, $direction);
        } else {
            $query->orderBy('start_date', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new blocked date for a single room unit.
     */
    public function createBlockedDate(array $data): RoomUnitBlockedDate
    {
        $this->validateBlockedDateData($data);

        return DB::transaction(function () use ($data) {
            $blockedDate = RoomUnitBlockedDate::create([
                'room_unit_id' => $data['room_unit_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'expiry_date' => $data['expiry_date'],
                'active' => $data['active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            Log::info("Created blocked date for room unit {$data['room_unit_id']}", [
                'blocked_date_id' => $blockedDate->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'expiry_date' => $data['expiry_date'],
            ]);

            return $blockedDate;
        });
    }

    /**
     * Create blocked dates for multiple room units (bulk operation).
     */
    public function createBulkBlockedDates(array $roomUnitIds, array $data): Collection
    {
        $this->validateBlockedDateData($data, null, true);

        return DB::transaction(function () use ($roomUnitIds, $data) {
            $createdIds = [];

            foreach ($roomUnitIds as $roomUnitId) {
                // Get room unit information for better error messages
                $roomUnit = RoomUnit::with('room')->find($roomUnitId);
                if (!$roomUnit) {
                    throw new \InvalidArgumentException("Room unit with ID {$roomUnitId} not found");
                }

                // Check for overlapping blocked dates for this specific room unit
                $hasOverlap = RoomUnitBlockedDate::where('room_unit_id', $roomUnitId)
                    ->where('active', true)
                    ->where(function ($q) use ($data) {
                        $startDate = Carbon::parse($data['start_date']);
                        $endDate = Carbon::parse($data['end_date']);
                        $q->where(function ($overlap) use ($startDate, $endDate) {
                            $overlap->where('start_date', '<=', $endDate->toDateString())
                                   ->where('end_date', '>=', $startDate->toDateString());
                        });
                    })
                    ->exists();

                if ($hasOverlap) {
                    throw new \InvalidArgumentException("Room unit {$roomUnit->room->name} - Unit {$roomUnit->unit_number} already has an active blocked date that overlaps with the specified date range");
                }

                // Check for existing bookings that would conflict with the blocked dates
                $startDate = Carbon::parse($data['start_date']);
                $endDate = Carbon::parse($data['end_date']);
                
                $hasConflictingBookings = $roomUnit->bookingRooms()
                    ->whereHas('booking', function ($query) use ($startDate, $endDate) {
                        $query->whereIn('status', ['paid', 'downpayment'])
                              ->where(function ($q) use ($startDate, $endDate) {
                                  // Overnight bookings: check_in < endDate AND check_out > startDate
                                  $q->where(function ($overnight) use ($startDate, $endDate) {
                                      $overnight->where('booking_type', '<>', 'day_tour')
                                                ->where('check_in_date', '<=', $endDate->toDateString())
                                                ->where('check_out_date', '>', $startDate->toDateString());
                                  })
                                  // Day tour bookings: check_in_date = startDate (same day booking)
                                  ->orWhere(function ($dayTour) use ($startDate) {
                                      $dayTour->where('booking_type', 'day_tour')
                                              ->where('check_in_date', $startDate->toDateString());
                                  });
                              });
                    })
                    ->exists();

                if ($hasConflictingBookings) {
                    throw new \InvalidArgumentException("Room unit {$roomUnit->room->name} - Unit {$roomUnit->unit_number} has existing bookings that conflict with the specified blocked date range. Please check existing bookings for this room unit.");
                }

                $blockedDate = RoomUnitBlockedDate::create([
                    'room_unit_id' => $roomUnitId,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'expiry_date' => $data['expiry_date'],
                    'active' => $data['active'] ?? true,
                    'notes' => $data['notes'] ?? null,
                ]);

                $createdIds[] = $blockedDate->id;
            }

            Log::info("Created bulk blocked dates", [
                'room_unit_count' => count($roomUnitIds),
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'expiry_date' => $data['expiry_date'],
            ]);

            // Return as Eloquent Collection with relationships loaded
            return RoomUnitBlockedDate::with('roomUnit.room')->whereIn('id', $createdIds)->get();
        });
    }

    /**
     * Update an existing blocked date.
     */
    public function updateBlockedDate(RoomUnitBlockedDate $blockedDate, array $data): RoomUnitBlockedDate
    {
        $this->validateBlockedDateData($data, $blockedDate);

        return DB::transaction(function () use ($blockedDate, $data) {
            $blockedDate->update([
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'expiry_date' => $data['expiry_date'],
                'active' => $data['active'] ?? $blockedDate->active,
                'notes' => $data['notes'] ?? $blockedDate->notes,
            ]);

            Log::info("Updated blocked date", [
                'blocked_date_id' => $blockedDate->id,
                'room_unit_id' => $blockedDate->room_unit_id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'expiry_date' => $data['expiry_date'],
            ]);

            return $blockedDate->fresh();
        });
    }

    /**
     * Delete a blocked date.
     */
    public function deleteBlockedDate(RoomUnitBlockedDate $blockedDate): bool
    {
        return DB::transaction(function () use ($blockedDate) {
            $roomUnitId = $blockedDate->room_unit_id;
            $blockedDateId = $blockedDate->id;

            $deleted = $blockedDate->delete();

            if ($deleted) {
                Log::info("Deleted blocked date", [
                    'blocked_date_id' => $blockedDateId,
                    'room_unit_id' => $roomUnitId,
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Toggle the active status of a blocked date.
     */
    public function toggleActiveStatus(RoomUnitBlockedDate $blockedDate): RoomUnitBlockedDate
    {
        return DB::transaction(function () use ($blockedDate) {
            $blockedDate->update(['active' => !$blockedDate->active]);

            Log::info("Toggled blocked date active status", [
                'blocked_date_id' => $blockedDate->id,
                'room_unit_id' => $blockedDate->room_unit_id,
                'new_status' => $blockedDate->active ? 'active' : 'inactive',
            ]);

            return $blockedDate->fresh();
        });
    }

    /**
     * Deactivate all expired blocked dates.
     */
    public function deactivateExpiredBlockedDates(): int
    {
        $expiredDates = RoomUnitBlockedDate::active()
            ->where('expiry_date', '<=', now()->toDateString())
            ->get();

        $deactivatedCount = 0;

        foreach ($expiredDates as $blockedDate) {
            $blockedDate->update(['active' => false]);
            $deactivatedCount++;
        }

        if ($deactivatedCount > 0) {
            Log::info("Deactivated expired blocked dates", [
                'count' => $deactivatedCount,
            ]);
        }

        return $deactivatedCount;
    }

    /**
     * Get statistics for blocked dates.
     */
    public function getBlockedDatesStats(): array
    {
        $total = RoomUnitBlockedDate::count();
        $active = RoomUnitBlockedDate::active()->count();
        $expired = RoomUnitBlockedDate::where('expiry_date', '<', now()->toDateString())->count();
        $expiringSoon = RoomUnitBlockedDate::active()
            ->where('expiry_date', '<=', now()->addDays(7)->toDateString())
            ->where('expiry_date', '>', now()->toDateString())
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
        ];
    }

    /**
     * Validate blocked date data.
     */
    private function validateBlockedDateData(array $data, ?RoomUnitBlockedDate $existingBlockedDate = null, bool $isBulk = false): void
    {
        if (empty($data['start_date']) || empty($data['end_date']) || empty($data['expiry_date'])) {
            throw new \InvalidArgumentException('Start date, end date, and expiry date are required');
        }

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);
        $expiryDate = Carbon::parse($data['expiry_date']);

        if ($startDate > $endDate) {
            throw new \InvalidArgumentException('Start date cannot be after end date');
        }

        if ($expiryDate > $startDate) {
            throw new \InvalidArgumentException('Expiry date must be before or same as the blocked date (booking deadline)');
        }

        // Check for overlapping blocked dates (excluding current one if updating)
        // Skip overlap check for bulk operations as it will be checked per room unit
        if (!$isBulk && isset($data['room_unit_id'])) {
            $query = RoomUnitBlockedDate::where('room_unit_id', $data['room_unit_id'])
                ->where('active', true)
                ->where(function ($q) use ($startDate, $endDate) {
                    $q->where(function ($overlap) use ($startDate, $endDate) {
                        $overlap->where('start_date', '<=', $endDate->toDateString())
                               ->where('end_date', '>=', $startDate->toDateString());
                    });
                });

            if ($existingBlockedDate) {
                $query->where('id', '!=', $existingBlockedDate->id);
            }

            if ($query->exists()) {
                throw new \InvalidArgumentException('There is already an active blocked date that overlaps with the specified date range');
            }

            // Check for existing bookings that would conflict with the blocked dates
            $roomUnit = RoomUnit::find($data['room_unit_id']);
            if ($roomUnit) {
                $hasConflictingBookings = $roomUnit->bookingRooms()
                    ->whereHas('booking', function ($query) use ($startDate, $endDate) {
                        $query->whereIn('status', ['paid', 'downpayment'])
                              ->where(function ($q) use ($startDate, $endDate) {
                                  // Overnight bookings: check_in < endDate AND check_out > startDate
                                  $q->where(function ($overnight) use ($startDate, $endDate) {
                                      $overnight->where('booking_type', '<>', 'day_tour')
                                                ->where('check_in_date', '<=', $endDate->toDateString())
                                                ->where('check_out_date', '>', $startDate->toDateString());
                                  })
                                  // Day tour bookings: check_in_date = startDate (same day booking)
                                  ->orWhere(function ($dayTour) use ($startDate) {
                                      $dayTour->where('booking_type', 'day_tour')
                                              ->where('check_in_date', $startDate->toDateString());
                                  });
                              });
                    })
                    ->exists();

                if ($hasConflictingBookings) {
                    throw new \InvalidArgumentException('Cannot create blocked dates for dates that are already booked. Please check existing bookings for this room unit.');
                }
            }
        }
    }
}
