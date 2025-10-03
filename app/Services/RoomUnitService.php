<?php

namespace App\Services;

use App\Contracts\Repositories\RoomUnitRepositoryInterface;
use App\DTO\RoomUnits\GenerateUnitsData;
use App\Enums\RoomUnitStatusEnum;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomUnit;
use Carbon\Carbon;
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
        $allowedFields = ['status', 'notes', 'maintenance_start_at', 'maintenance_end_at', 'blocked_start_at', 'blocked_end_at'];
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

            return $unit;
        });
    }

    /**
     * Release a room unit when a booking is cancelled or completed.
     * Note: With date-based availability, this method is mainly for logging.
     */
    public function releaseUnit(RoomUnit $roomUnit): void
    {
        // Unit released - availability now tracked by booking dates
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

    /**
     * Get room unit calendar data for a specific month and year.
     * Returns data for overnight rooms only.
     */
    public function getRoomUnitCalendarData(int $year, int $month): array
    {
        $cacheKey = "room_unit_calendar_{$year}_{$month}";
        
        // Try to get from cache first
        $cached = cache()->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Get days in month using Carbon
        $startOfMonth = Carbon::create($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        $days = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $days[] = $day;
        }

        // Step 1: Get all overnight room units with room names in a single optimized query
        $roomUnits = DB::table('room_units')
            ->join('rooms', 'room_units.room_id', '=', 'rooms.id')
            ->where('rooms.room_type', 'overnight')
            ->orderBy('room_units.room_id')
            ->orderByRaw('CAST(room_units.unit_number AS UNSIGNED)')
            ->select([
                'room_units.id',
                'room_units.room_id',
                'room_units.unit_number',
                'room_units.maintenance_start_at',
                'room_units.maintenance_end_at',
                'room_units.blocked_start_at',
                'room_units.blocked_end_at',
                'rooms.name as room_name'
            ])
            ->get();

        if ($roomUnits->isEmpty()) {
            $result = [
                'year' => $year,
                'month' => $month,
                'days' => $days,
                'rooms' => []
            ];
            cache()->put($cacheKey, $result, 300); // Cache for 5 minutes
            return $result;
        }

        // Extract unit IDs for batch loading
        $unitIds = $roomUnits->pluck('id')->toArray();
        
        // Step 2: Batch load ALL bookings for ALL units for the entire month in ONE optimized query
        $bookings = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->whereIn('booking_rooms.room_unit_id', $unitIds)
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                // Overnight bookings that overlap with the month
                $q->where(function ($overnight) use ($startOfMonth, $endOfMonth) {
                    $overnight->where('bookings.booking_type', '<>', 'day_tour')
                              ->where('bookings.check_in_date', '<=', $endOfMonth->toDateString())
                              ->where('bookings.check_out_date', '>', $startOfMonth->toDateString());
                })
                // Day tour bookings within the month
                ->orWhere(function ($dayTour) use ($startOfMonth, $endOfMonth) {
                    $dayTour->where('bookings.booking_type', 'day_tour')
                            ->whereBetween('bookings.check_in_date', [
                                $startOfMonth->toDateString(), 
                                $endOfMonth->toDateString()
                            ]);
                });
            })
            ->whereNull('bookings.deleted_at')
            ->select([
                'booking_rooms.room_unit_id',
                'bookings.id as booking_id',
                'bookings.status',
                'bookings.booking_type',
                'bookings.booking_source',
                'bookings.check_in_date',
                'bookings.check_out_date'
            ])
            ->get()
            ->groupBy('room_unit_id');

        // Step 3: Process data in memory (no more database queries)
        $roomsData = [];
        foreach ($roomUnits as $unit) {
            $roomId = $unit->room_id;
            $roomName = $unit->room_name;

            if (!isset($roomsData[$roomId])) {
                $roomsData[$roomId] = [
                    'room_id' => $roomId,
                    'room_name' => $roomName,
                    'units' => []
                ];
            }

            // Get bookings for this unit
            $unitBookings = $bookings->get($unit->id, collect());
            
            // Calculate day statuses using optimized in-memory processing
            $dayStatuses = $this->calculateDayStatusesOptimized($unit, $unitBookings, $year, $month, $days);

            $roomsData[$roomId]['units'][] = [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'current_status' => 'available', // Default status since we're not loading full model
                'notes' => null, // Not needed for calendar display
                'maintenance_start_at' => $unit->maintenance_start_at,
                'maintenance_end_at' => $unit->maintenance_end_at,
                'blocked_start_at' => $unit->blocked_start_at,
                'blocked_end_at' => $unit->blocked_end_at,
                'day_statuses' => $dayStatuses
            ];
        }

        $result = [
            'year' => $year,
            'month' => $month,
            'days' => $days,
            'rooms' => array_values($roomsData)
        ];

        // Cache the result for 5 minutes
        cache()->put($cacheKey, $result, 300);
        
        return $result;
    }

    /**
     * Get day tour room unit calendar data for a specific month and year.
     * Returns data for day tour rooms only.
     */
    public function getDayTourRoomUnitCalendarData(int $year, int $month): array
    {
        $cacheKey = "day_tour_calendar_{$year}_{$month}";
        
        // Try to get from cache first
        $cached = cache()->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Get days in month using Carbon
        $startOfMonth = Carbon::create($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $daysInMonth = $startOfMonth->daysInMonth;
        $days = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $days[] = $day;
        }

        // Step 1: Get all day tour room units with room names in a single optimized query
        $roomUnits = DB::table('room_units')
            ->join('rooms', 'room_units.room_id', '=', 'rooms.id')
            ->where('rooms.room_type', 'day_tour')
            ->orderBy('room_units.room_id')
            ->orderByRaw('CAST(room_units.unit_number AS UNSIGNED)')
            ->select([
                'room_units.id',
                'room_units.room_id',
                'room_units.unit_number',
                'room_units.maintenance_start_at',
                'room_units.maintenance_end_at',
                'room_units.blocked_start_at',
                'room_units.blocked_end_at',
                'rooms.name as room_name'
            ])
            ->get();

        if ($roomUnits->isEmpty()) {
            $result = [
                'year' => $year,
                'month' => $month,
                'days' => $days,
                'rooms' => []
            ];
            cache()->put($cacheKey, $result, 300); // Cache for 5 minutes
            return $result;
        }

        // Extract unit IDs for batch loading
        $unitIds = $roomUnits->pluck('id')->toArray();
        
        // Step 2: Batch load ALL day tour bookings for ALL units for the entire month in ONE optimized query
        $bookings = DB::table('booking_rooms')
            ->join('bookings', 'booking_rooms.booking_id', '=', 'bookings.id')
            ->whereIn('booking_rooms.room_unit_id', $unitIds)
            ->where('bookings.booking_type', 'day_tour')
            ->whereBetween('bookings.check_in_date', [
                $startOfMonth->toDateString(), 
                $endOfMonth->toDateString()
            ])
            ->whereNull('bookings.deleted_at')
            ->select([
                'booking_rooms.room_unit_id',
                'bookings.id as booking_id',
                'bookings.status',
                'bookings.booking_type',
                'bookings.booking_source',
                'bookings.check_in_date',
                'bookings.check_out_date'
            ])
            ->get()
            ->groupBy('room_unit_id');

        // Step 3: Process data in memory (no more database queries)
        $roomsData = [];
        foreach ($roomUnits as $unit) {
            $roomId = $unit->room_id;
            $roomName = $unit->room_name;

            if (!isset($roomsData[$roomId])) {
                $roomsData[$roomId] = [
                    'room_id' => $roomId,
                    'room_name' => $roomName,
                    'units' => []
                ];
            }

            // Get bookings for this unit
            $unitBookings = $bookings->get($unit->id, collect());
            
            // Calculate day statuses using optimized in-memory processing
            $dayStatuses = $this->calculateDayStatusesOptimized($unit, $unitBookings, $year, $month, $days);

            $roomsData[$roomId]['units'][] = [
                'id' => $unit->id,
                'unit_number' => $unit->unit_number,
                'current_status' => 'available', // Default status since we're not loading full model
                'notes' => null, // Not needed for calendar display
                'maintenance_start_at' => $unit->maintenance_start_at,
                'maintenance_end_at' => $unit->maintenance_end_at,
                'blocked_start_at' => $unit->blocked_start_at,
                'blocked_end_at' => $unit->blocked_end_at,
                'day_statuses' => $dayStatuses
            ];
        }

        $result = [
            'year' => $year,
            'month' => $month,
            'days' => $days,
            'rooms' => array_values($roomsData)
        ];

        // Cache the result for 5 minutes
        cache()->put($cacheKey, $result, 300);
        
        return $result;
    }

    /**
     * Get available room units for a specific room and date range.
     * This method is used by the admin interface to show available units for reassignment.
     * Uses efficient database-level filtering instead of PHP filtering.
     */
    public function getAvailableUnitsForReassignment(int $roomId, string $checkInDate, string $checkOutDate): Collection
    {
        return RoomUnit::query()
            ->where('room_id', $roomId)
            ->whereNotIn('status', ['maintenance', 'blocked'])
            ->whereDoesntHave('bookingRooms.booking', function ($q) use ($checkInDate, $checkOutDate) {
                // Include only bookings that can block a unit
                $q->where(function ($statusQ) {
                    $statusQ->whereIn('status', ['paid', 'downpayment'])
                            ->orWhere(function ($p) {
                                $p->where('status', 'pending')
                                  ->whereNotNull('booking_rooms.room_unit_id'); // already assigned
                            });
                })
                // Date overlap: overnight OR day-tour same day
                ->where(function ($dateQ) use ($checkInDate, $checkOutDate) {
                    $dateQ->where(function ($overnight) use ($checkInDate, $checkOutDate) {
                            $overnight->where('booking_type', '<>', 'day_tour')
                                      ->where('check_in_date', '<', $checkOutDate)
                                      ->where('check_out_date', '>', $checkInDate);
                        })
                        ->orWhere(function ($dayTour) use ($checkInDate) {
                            $dayTour->where('booking_type', 'day_tour')
                                    ->where('check_in_date', $checkInDate);
                        });
                });
            })
            // Avoid CAST if possible by storing unit_number as INT; otherwise keep this:
            ->orderByRaw('CAST(unit_number AS UNSIGNED)')
            ->get();
    }


    /**
     * Get booking data for a specific unit and date.
     * This method is called on-demand when user clicks on a booked date.
     */
    public function getBookingDataForUnitAndDate(int $unitId, string $date): ?array
    {
        $unit = RoomUnit::with([
            'room',
            'bookingRooms.booking' => function ($query) {
                $query->with(['payments', 'otherCharges']);
            }
        ])->find($unitId);

        if (!$unit) {
            return null;
        }

        return $this->getBookingDataForDate($unit, $date);
    }

    /**
     * Get day tour booking data for a specific unit and date.
     * This method is called on-demand when user clicks on a booked date for day tour units.
     */
    public function getDayTourBookingDataForUnitAndDate(int $unitId, string $date): ?array
    {
        $unit = RoomUnit::with([
            'room',
            'bookingRooms.booking' => function ($query) {
                $query->with(['payments', 'otherCharges']);
            }
        ])->find($unitId);

        if (!$unit) {
            return null;
        }

        return $this->getDayTourBookingDataForDate($unit, $date);
    }

    /**
     * Get booking data for a specific unit and date (private helper).
     */
    private function getBookingDataForDate(RoomUnit $unit, string $date): ?array
    {
        $bookingRoom = $unit->bookingRooms()
            ->whereHas('booking', function ($query) use ($date) {
                $query->whereIn('status', ['paid', 'downpayment', 'pending'])
                      ->where('check_in_date', '<=', $date)
                      ->where('check_out_date', '>', $date);
            })
            ->with(['booking.payments', 'booking.otherCharges'])
            ->first();

        if (!$bookingRoom) {
            return null;
        }

        $booking = $bookingRoom->booking;
        
        // Calculate remaining balance
        $totalPaid = $booking->payments->where('status', 'paid')->sum('amount');
        $otherCharges = $booking->otherCharges->sum('amount');
        // Calculate actual final price after discount and PWD/Senior discount, then add other charges
        $actualFinalPrice = $booking->final_price - $booking->discount_amount - $booking->pwd_senior_discount;
        $totalPayable = $actualFinalPrice + $otherCharges;
        $remainingBalance = max(0, $totalPayable - $totalPaid);
        
        // Calculate nights
        $checkIn = Carbon::parse($booking->check_in_date);
        $checkOut = Carbon::parse($booking->check_out_date);
        $nights = $checkIn->diffInDays($checkOut);

        return [
            'id' => $booking->id,
            'reference_number' => $booking->reference_number,
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'special_requests' => $booking->special_requests,
            'check_in_date' => $booking->check_in_date,
            'check_out_date' => $booking->check_out_date,
            'nights' => $nights,
            'adults' => $booking->adults,
            'children' => $booking->children,
            'total_guests' => $booking->total_guests,
            'room_price' => $booking->total_price,
            'meal_price' => $booking->meal_price,
            'final_price' => $booking->final_price,
            'extra_guest_fee' => $booking->extra_guest_fee,
            'extra_guest_count' => $booking->extra_guest_count,
            'discount_amount' => $booking->discount_amount,
            'pwd_senior_discount' => $booking->pwd_senior_discount,
            'downpayment_amount' => $booking->downpayment_amount,
            'other_charges' => $otherCharges,
            'total_payable' => $totalPayable,
            'total_paid' => $totalPaid,
            'remaining_balance' => $remainingBalance,
            'status' => $booking->status,
            'booking_type' => $booking->booking_type,
            'booking_source' => $booking->booking_source,
        ];
    }

    /**
     * Get day tour booking data for a specific unit and date (private helper).
     */
    private function getDayTourBookingDataForDate(RoomUnit $unit, string $date): ?array
    {
        $bookingRoom = $unit->bookingRooms()
            ->whereHas('booking', function ($query) use ($date) {
                $query->whereIn('status', ['paid', 'downpayment', 'pending'])
                      ->where('booking_type', 'day_tour')
                      ->where('check_in_date', $date); // Day tour bookings are same day
            })
            ->with(['booking.payments', 'booking.otherCharges'])
            ->first();

        if (!$bookingRoom) {
            return null;
        }

        $booking = $bookingRoom->booking;
        
        // Calculate remaining balance
        $totalPaid = $booking->payments->where('status', 'paid')->sum('amount');
        $otherCharges = $booking->otherCharges->sum('amount');
        // Calculate actual final price after discount and PWD/Senior discount, then add other charges
        $actualFinalPrice = $booking->final_price - $booking->discount_amount - $booking->pwd_senior_discount;
        $totalPayable = $actualFinalPrice + $otherCharges;
        $remainingBalance = max(0, $totalPayable - $totalPaid);
        
        // Day tour bookings are always 0 nights
        $nights = 0;

        return [
            'id' => $booking->id,
            'reference_number' => $booking->reference_number,
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'special_requests' => $booking->special_requests,
            'check_in_date' => $booking->check_in_date,
            'check_out_date' => $booking->check_out_date,
            'nights' => $nights,
            'adults' => $booking->adults,
            'children' => $booking->children,
            'total_guests' => $booking->total_guests,
            'room_price' => $booking->total_price,
            'meal_price' => $booking->meal_price,
            'final_price' => $booking->final_price,
            'extra_guest_fee' => $booking->extra_guest_fee,
            'extra_guest_count' => $booking->extra_guest_count,
            'discount_amount' => $booking->discount_amount,
            'pwd_senior_discount' => $booking->pwd_senior_discount,
            'downpayment_amount' => $booking->downpayment_amount,
            'other_charges' => $otherCharges,
            'total_payable' => $totalPayable,
            'total_paid' => $totalPaid,
            'remaining_balance' => $remainingBalance,
            'status' => $booking->status,
            'booking_type' => $booking->booking_type,
            'booking_source' => $booking->booking_source,
            // Day tour specific fields
            'include_lunch' => $bookingRoom->include_lunch ?? false,
            'include_pm_snack' => $bookingRoom->include_pm_snack ?? false,
            'include_dinner' => $bookingRoom->include_dinner ?? false,
            'lunch_cost' => $bookingRoom->lunch_cost ?? 0,
            'pm_snack_cost' => $bookingRoom->pm_snack_cost ?? 0,
            'dinner_cost' => $bookingRoom->dinner_cost ?? 0,
            'meal_cost' => $bookingRoom->meal_cost ?? 0,
            'base_price' => $bookingRoom->base_price ?? 0,
        ];
    }

    /**
     * Calculate day statuses using optimized in-memory processing.
     * This method processes all days for a unit without additional database queries.
     */
    private function calculateDayStatusesOptimized($unit, $unitBookings, int $year, int $month, array $days): array
    {
        $dayStatuses = [];
        $isInMaintenance = $unit->maintenance_start_at && $unit->maintenance_end_at;
        $isBlocked = $unit->blocked_start_at && $unit->blocked_end_at;
        
        // Create a lookup map for bookings by date for O(1) access
        $bookingMap = [];
        foreach ($unitBookings as $booking) {
            if ($booking->status === 'cancelled') {
                continue;
            }
            
            if ($booking->booking_type === 'day_tour') {
                // Day tour bookings are for a single day
                $bookingMap[$booking->check_in_date] = $booking;
            } else {
                // Overnight bookings span multiple days
                $checkIn = Carbon::parse($booking->check_in_date);
                $checkOut = Carbon::parse($booking->check_out_date);
                $current = $checkIn->copy();
                
                while ($current->lt($checkOut)) {
                    $dateStr = $current->format('Y-m-d');
                    if (!isset($bookingMap[$dateStr])) {
                        $bookingMap[$dateStr] = $booking;
                    }
                    $current->addDay();
                }
            }
        }
        
        // Process each day
        foreach ($days as $day) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            
            // Check maintenance first (highest priority)
            if ($isInMaintenance) {
                $maintenanceStart = Carbon::parse($unit->maintenance_start_at);
                $maintenanceEnd = Carbon::parse($unit->maintenance_end_at);
                if (Carbon::parse($date)->between($maintenanceStart, $maintenanceEnd)) {
                    $dayStatuses[] = [
                        'day' => $day,
                        'date' => $date,
                        'status' => 'maintenance',
                        'booking_source' => null
                    ];
                    continue;
                }
            }
            
            // Check blocked second
            if ($isBlocked) {
                $blockedStart = Carbon::parse($unit->blocked_start_at);
                $blockedEnd = Carbon::parse($unit->blocked_end_at);
                if (Carbon::parse($date)->between($blockedStart, $blockedEnd)) {
                    $dayStatuses[] = [
                        'day' => $day,
                        'date' => $date,
                        'status' => 'blocked',
                        'booking_source' => null
                    ];
                    continue;
                }
            }
            
            // Check for bookings
            if (isset($bookingMap[$date])) {
                $booking = $bookingMap[$date];
                $status = in_array($booking->status, ['paid', 'downpayment']) ? 'booked' : 'pending';
                $dayStatuses[] = [
                    'day' => $day,
                    'date' => $date,
                    'status' => $status,
                    'booking_source' => $booking->booking_source
                ];
            } else {
                $dayStatuses[] = [
                    'day' => $day,
                    'date' => $date,
                    'status' => 'available',
                    'booking_source' => null
                ];
            }
        }
        
        return $dayStatuses;
    }

    /**
     * Clear calendar cache for a specific month and year.
     */
    public function clearCalendarCache(int $year, int $month): void
    {
        $overnightCacheKey = "room_unit_calendar_{$year}_{$month}";
        $dayTourCacheKey = "day_tour_calendar_{$year}_{$month}";
        
        cache()->forget($overnightCacheKey);
        cache()->forget($dayTourCacheKey);
    }

    /**
     * Clear all calendar cache for current and next year.
     */
    public function clearAllCalendarCache(): void
    {
        $currentYear = now()->year;
        $nextYear = $currentYear + 1;
        
        for ($year = $currentYear; $year <= $nextYear; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $this->clearCalendarCache($year, $month);
            }
        }
    }
}
