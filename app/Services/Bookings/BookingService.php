<?php

namespace App\Services\Bookings;

use App\Actions\Bookings\CalculateBookingTotalAction;
use App\Actions\Bookings\CheckRoomAvailabilityAction;
use App\Actions\Bookings\CreateBookingEntitiesAction;
use App\Actions\Bookings\SetBookingLockAction;
use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Services\BookingServiceInterface;
use App\DTO\Bookings\BookingData;
use App\DTO\Bookings\BookingRoomData;
use App\Exceptions\BookingAlreadyClaimedException;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\RoomUnit;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingService implements BookingServiceInterface
{
    public function __construct(
        private CheckRoomAvailabilityAction $checkAvailability,
        private CalculateBookingTotalAction $calcTotal,
        private CreateBookingEntitiesAction $createEntities,
        private SetBookingLockAction $setLock,
        private BookingRepositoryInterface $bookingRepo,
    ) {}

    /**
     * List paginated rooms (all statuses or filtered).
     */
    public function list(array $filters): LengthAwarePaginator
    {

        return $this->bookingRepo->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

    /**
     * List paginated rooms (all statuses or filtered).
     */
    public function listByUser(int $userId, array $filters): LengthAwarePaginator
    {
        $filters['user_id'] = $userId;
        return $this->bookingRepo->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

    /**
     * Show one Room by ID (throws ModelNotFoundException if missing).
     */
    public function show(int $id): Booking
    {
        return $this->bookingRepo->getId($id);
    }
    
    public function createBooking(BookingData $bookingData, ?int $userId = null)
    {
        $bookingRoomArr = array_map(fn($rd) => (object) $rd, $bookingData->rooms);

        $booking = DB::transaction(function () use ($bookingData, $bookingRoomArr, $userId) {
            // 1. Check availability for all requested rooms
            $this->checkAvailability->execute(
                $bookingRoomArr,
                $bookingData->check_in_date,
                $bookingData->check_out_date
            );
            // 2. Calculate total price (rooms + meals)
            $totals = $this->calcTotal->execute(
                $bookingRoomArr,
                $bookingData->check_in_date,
                $bookingData->check_out_date,
                $bookingData->total_adults,
                $bookingData->total_children
            );
            // 3. Create booking + booking_rooms
            $booking = $this->createEntities->execute($bookingData, $bookingRoomArr, $userId, $totals);

            // 4. Lock the booking in Redis
            $this->setLock->execute(
                $booking->id,
                $bookingRoomArr,
                $bookingData->check_in_date,
                $bookingData->check_out_date
            );

            return $booking->refresh();
        });

        Mail::to($bookingData->guest_email)->queue(new \App\Mail\BookingReservation($booking));

        return $booking;
    }

    public function showByReferenceNumber(string $referenceNumber): Booking
    {
        return $this->bookingRepo->getByReferenceNumber($referenceNumber);
    }

    public function markPaid(Booking $booking): void
    {
        // Sum all successful payments
        $paidAmount = $booking->payments()->where('status', 'paid')->sum('amount');
        $finalPrice = $booking->final_price;
        // Get DP percent from config, fallback to 0.5 if not set
        $dpPercent = config('booking.downpayment_percent', 0.5);
        $dpAmount = $finalPrice * $dpPercent;

        
        $previousStatus = $booking->status;
        $newStatus = null;
        
        if ($paidAmount >= $finalPrice) {
            $booking->update(['status' => 'paid', 'paid_at' => now()]);
            $newStatus = 'paid';
        } elseif ($paidAmount >= $dpAmount) {
            $booking->update(['status' => 'downpayment', 'downpayment_at' => now()]);
            $newStatus = 'downpayment';
        } else {
            $booking->update(['status' => 'pending']);
            $newStatus = 'pending';
        }

        // If booking just became confirmed (first time reaching downpayment or paid status), assign room units
        if ($previousStatus === 'pending' && in_array($newStatus, ['downpayment', 'paid'])) {
            $confirmBookingAction = app(\App\Actions\Bookings\ConfirmBookingAction::class);
            $allUnitsAssigned = $confirmBookingAction->execute($booking->refresh());
            
            if (!$allUnitsAssigned) {
                Log::warning("Not all room units could be assigned for booking {$booking->reference_number}");
                // Could potentially send a notification to staff here
            }
        }
    }

    public function markPaymentFailed(Booking $booking): void
    {
        $booking->increment('failed_payment_attempts');
        $booking->update(['last_payment_failed_at' => now()]);
    }
    
    public function claimBooking(string $referenceNumber, int $userId): Booking
    {
        // Find booking or throw ModelNotFound
        $booking = $this->bookingRepo->getByReferenceNumber($referenceNumber);

        if ($booking->user_id !== null) {
            // Already associated with a user, cannot claim
            throw new BookingAlreadyClaimedException("Booking already claimed by another user.", 422);
        }

        // Update user_id to assign the booking to the current user
        $booking->user_id = $userId;
        $booking->save();  // or $this->bookingRepo->save($booking) if such method exists

        return $booking;
    }

    /**
     * Build calendar dataset for bookings within a given date range.
     * Uses Eloquent models and relations (no DB facade).
     * Overlap logic: include bookings where check_in_date < end AND check_out_date > start.
     */
    public function getCalendar(array $params): array
    {
        $start = data_get($params, 'start');
        $end = data_get($params, 'end');
        $statusParam = data_get($params, 'status');
        $roomTypeId = data_get($params, 'room_type_id');

        if (!$start || !$end) {
            throw new \InvalidArgumentException('start and end are required (YYYY-MM-DD).');
        }

        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->endOfDay();
        if ($startDate->gt($endDate)) {
            throw new \InvalidArgumentException('start must be before end.');
        }
        
        // Check if this is a day view (same start and end date)
        $isDayView = $startDate->toDateString() === $endDate->toDateString();

        $statuses = null;
        if ($statusParam) {
            $statuses = collect(explode(',', $statusParam))
                ->map(fn($s) => trim($s))
                ->filter()->values()->all();
        }

        // Events: one per booking_room (prefer assigned unit data)
        $eventRows = BookingRoom::query()
            ->with([
                'booking:id,check_in_date,check_out_date,status,guest_name,total_price,final_price,adults,children,total_guests,reference_number,deleted_at',
                'room:id,name,max_guests',
                'roomUnit:id,unit_number',
            ])
            ->whereHas('booking', function ($q) use ($startDate, $endDate, $statuses, $isDayView) {
                if ($isDayView) {
                    // For day view: show bookings that are active on this specific day
                    // A booking is active if: check_in_date <= selected_date AND check_out_date > selected_date
                    // This excludes the checkout day from being shown
                    $q->where('check_in_date', '<=', $startDate->toDateString())
                      ->where('check_out_date', '>', $startDate->toDateString());
                } else {
                    // For month view: show all overlapping bookings (excluding checkout days)
                    $q->where('check_in_date', '<=', $endDate->toDateString())
                      ->where('check_out_date', '>', $startDate->toDateString());
                }
                
                if (is_array($statuses) && count($statuses) > 0) {
                    $q->whereIn('status', $statuses);
                } else {
                    $q->whereNotIn('status', ['cancelled', 'failed']);
                }
            })
            ->when($roomTypeId, fn($q) => $q->where('room_id', (int) $roomTypeId))
            ->get();

        $events = [];
        foreach ($eventRows as $br) {
            $booking = $br->booking;
            if (!$booking) continue;
            
            $nights = \Carbon\Carbon::parse($booking->check_in_date)
                ->diffInDays(\Carbon\Carbon::parse($booking->check_out_date));
            
            // Calculate remaining balance
            $paidAmount = $booking->payments()->where('status', 'paid')->sum('amount');
            $remainingBalance = max(0, $booking->final_price - $paidAmount);
            
            $events[] = [
                'booking_id' => (int) $booking->id,
                'reference_number' => $booking->reference_number,
                'room_type_id' => $br->room?->id,
                'room_type_name' => $br->room?->name ?? 'Unassigned Room',
                'room_unit_id' => $br->roomUnit?->id,
                'room_unit_number' => $br->roomUnit?->unit_number ?? 'TBD',
                'room_capacity' => $br->room?->max_guests ?? 2,
                'room_price_per_night' => $br->price_per_night,
                'guest_name' => $booking->guest_name,
                'status' => $booking->status,
                'start' => $booking->check_in_date,
                'end' => $booking->check_out_date,
                'nights' => $nights,
                'total_price' => $booking->total_price,
                'final_price' => $booking->final_price,
                'remaining_balance' => $remainingBalance,
                'adults' => $br->adults,
                'children' => $br->children,
                'total_guests' => $br->total_guests,
                'booking_adults' => $booking->adults,
                'booking_children' => $booking->children,
                'booking_total_guests' => $booking->total_guests,
            ];
        }

        // Occupancy: count unique bookings per night (not individual rooms)
        $bookingRows = Booking::query()
            ->where(function($q) use ($startDate, $endDate, $isDayView) {
                if ($isDayView) {
                    // For day view: show bookings that are active on this specific day
                    // A booking is active if: check_in_date <= selected_date AND check_out_date > selected_date
                    // This excludes the checkout day from being counted
                    $q->where('check_in_date', '<=', $startDate->toDateString())
                      ->where('check_out_date', '>', $startDate->toDateString());
                } else {
                    // For month view: show all overlapping bookings (excluding checkout days)
                    $q->where('check_in_date', '<=', $endDate->toDateString())
                      ->where('check_out_date', '>', $startDate->toDateString());
                }
            })
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->when(is_array($statuses) && count($statuses) > 0, fn($q) => $q->whereIn('status', $statuses))
            ->when($roomTypeId, function($q) use ($roomTypeId) {
                $q->whereHas('bookingRooms', function($subQ) use ($roomTypeId) {
                    $subQ->where('room_id', (int) $roomTypeId);
                });
            })
            ->get();

        // Total bookable units (filter by room type if provided)
        $unitsQuery = RoomUnit::query()->bookable();
        if ($roomTypeId) $unitsQuery->where('room_id', (int) $roomTypeId);
        $totalUnits = (int) $unitsQuery->count();

        // Build per-day summary
        $summaryMap = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $summaryMap[$cursor->toDateString()] = 0;
            $cursor->addDay();
        }
        
        foreach ($bookingRows as $booking) {
            $s = Carbon::parse($booking->check_in_date);
            $e = Carbon::parse($booking->check_out_date);
            
            // Count each day the booking spans (excluding checkout day)
            $startLoop = $s->max($startDate);
            $endLoop = $e->min($endDate);
            $day = $startLoop->copy();
            
            while ($day->lt($endLoop)) { // Use < instead of <= to exclude checkout day
                $key = $day->toDateString();
                if (array_key_exists($key, $summaryMap)) {
                    $summaryMap[$key] += 1;
                }
                $day->addDay();
            }
        }

        $summary = [];
        foreach ($summaryMap as $date => $count) {
            $summary[] = [
                'date' => $date,
                'bookings' => (int) $count,
                'rooms_left' => max(0, $totalUnits - (int) $count),
            ];
        }

        return [
            'summary' => $summary,
            'events' => $events,
        ];
    }
}
