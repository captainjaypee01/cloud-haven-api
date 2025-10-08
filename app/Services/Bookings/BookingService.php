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
use App\Models\Promo;
use App\Models\RoomUnit;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailTrackingService;
use App\Services\PromoCalculationService;

class BookingService implements BookingServiceInterface
{
    public function __construct(
        private CheckRoomAvailabilityAction $checkAvailability,
        private CalculateBookingTotalAction $calcTotal,
        private CreateBookingEntitiesAction $createEntities,
        private SetBookingLockAction $setLock,
        private BookingRepositoryInterface $bookingRepo,
        private PromoCalculationService $promoCalculationService,
    ) {}

    /**
     * List paginated bookings (all statuses or filtered).
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
        Log::info('Starting booking creation process', [
            'guest_email' => $bookingData->guest_email,
            'guest_name' => $bookingData->guest_name,
            'check_in_date' => $bookingData->check_in_date,
            'check_out_date' => $bookingData->check_out_date,
            'total_adults' => $bookingData->total_adults,
            'total_children' => $bookingData->total_children,
            'user_id' => $userId,
            'room_count' => count($bookingData->rooms)
        ]);

        $bookingRoomArr = array_map(fn($rd) => (object) $rd, $bookingData->rooms);

        $booking = DB::transaction(function () use ($bookingData, $bookingRoomArr, $userId) {
            // 1. Check availability for all requested rooms
            $this->checkAvailability->execute(
                $bookingRoomArr,
                $bookingData->check_in_date,
                $bookingData->check_out_date
            );
            
            // 2. Validate promo if provided (separate validation)
            $promo = null;
            if (!empty($bookingData->promo_id)) {
                $promo = Promo::find($bookingData->promo_id);
                if (!$promo) {
                    throw new \InvalidArgumentException('Promo code not found.');
                }
                
                // Validate promo using PromoCalculationService
                $validation = $this->promoCalculationService->validatePromoForDateRange(
                    $promo, 
                    $bookingData->check_in_date, 
                    $bookingData->check_out_date
                );
                
                if (!$validation['is_valid']) {
                    throw new \InvalidArgumentException('Promo code is not valid for the selected dates: ' . implode(' ', $validation['errors']));
                }
                
                Log::info('Promo validated successfully for booking', [
                    'promo_id' => $promo->id,
                    'promo_code' => $promo->code,
                    'check_in_date' => $bookingData->check_in_date,
                    'check_out_date' => $bookingData->check_out_date
                ]);
            }
            
            // 3. Calculate total price (rooms + meals) with promo
            $totals = $this->calcTotal->execute(
                $bookingRoomArr,
                $bookingData->check_in_date,
                $bookingData->check_out_date,
                $bookingData->total_adults,
                $bookingData->total_children,
                $promo
            );
            // 4. Create booking + booking_rooms
            $booking = $this->createEntities->execute($bookingData, $bookingRoomArr, $userId, $totals);

            // 5. Lock the booking in Redis
            $this->setLock->execute(
                $booking->id,
                $bookingRoomArr,
                $bookingData->check_in_date,
                $bookingData->check_out_date
            );

            return $booking->refresh();
        });

        Log::info('Booking created successfully', [
            'booking_id' => $booking->id,
            'reference_number' => $booking->reference_number,
            'total_price' => $booking->total_price,
            'final_price' => $booking->final_price,
            'meal_price' => $booking->meal_price,
            'extra_guest_fee' => $booking->extra_guest_fee,
            'extra_guest_count' => $booking->extra_guest_count,
            'guest_name' => $bookingData->guest_name,
            'check_in_date' => $bookingData->check_in_date,
            'check_out_date' => $bookingData->check_out_date
        ]);

        EmailTrackingService::sendWithTracking(
            $bookingData->guest_email,
            new \App\Mail\BookingReservation($booking),
            'booking_reservation',
            [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'guest_name' => $bookingData->guest_name,
                'check_in_date' => $bookingData->check_in_date,
                'check_out_date' => $bookingData->check_out_date
            ]
        );

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
        
        // Calculate the actual final price after discount and including other charges
        $actualFinalPrice = $booking->final_price - $booking->discount_amount - $booking->pwd_senior_discount - $booking->special_discount;
        $otherCharges = $booking->otherCharges()->sum('amount');
        $totalPayable = $actualFinalPrice + $otherCharges;
        
        // Get DP percent from config, fallback to 0.5 if not set
        $dpPercent = config('booking.downpayment_percent', 0.5);
        $dpAmount = $totalPayable * $dpPercent;

        // Check if there are any payments manually marked as downpayment
        $hasManualDownpayment = $booking->payments()
            ->where('status', 'paid')
            ->where('downpayment_status', 'downpayment')
            ->exists();

        $previousStatus = $booking->status;
        $newStatus = null;
        
        Log::info('Processing payment status update', [
            'booking_id' => $booking->id,
            'reference_number' => $booking->reference_number,
            'previous_status' => $previousStatus,
            'paid_amount' => $paidAmount,
            'total_payable' => $totalPayable,
            'downpayment_amount' => $dpAmount,
            'has_manual_downpayment' => $hasManualDownpayment
        ]);
        
        if ($paidAmount >= $totalPayable) {
            $booking->update([
                'status' => 'paid', 
                'paid_at' => now(),
                'paid_amount' => $paidAmount
            ]);
            $newStatus = 'paid';
        } elseif ($paidAmount >= $dpAmount || $hasManualDownpayment) {
            $booking->update([
                'status' => 'downpayment', 
                'downpayment_at' => now(),
                'paid_amount' => $paidAmount,
                'downpayment_amount' => $dpAmount
            ]);
            $newStatus = 'downpayment';
        } else {
            $booking->update([
                'status' => 'pending',
                'paid_amount' => $paidAmount
            ]);
            $newStatus = 'pending';
        }

        Log::info('Booking status updated', [
            'booking_id' => $booking->id,
            'reference_number' => $booking->reference_number,
            'status_changed_from' => $previousStatus,
            'status_changed_to' => $newStatus,
            'paid_amount' => $paidAmount
        ]);

        // If booking just became confirmed (first time reaching downpayment or paid status), assign room units
        if ($previousStatus === 'pending' && in_array($newStatus, ['downpayment', 'paid'])) {
            Log::info('Booking confirmed - assigning room units', [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'new_status' => $newStatus
            ]);
            
            $confirmBookingAction = app(\App\Actions\Bookings\ConfirmBookingAction::class);
            $allUnitsAssigned = $confirmBookingAction->execute($booking->refresh());
            
            if (!$allUnitsAssigned) {
                Log::warning("Not all room units could be assigned for booking {$booking->reference_number}", [
                    'booking_id' => $booking->id,
                    'reference_number' => $booking->reference_number
                ]);
                // Could potentially send a notification to staff here
            } else {
                Log::info('All room units assigned successfully', [
                    'booking_id' => $booking->id,
                    'reference_number' => $booking->reference_number
                ]);
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
                'booking:id,check_in_date,check_out_date,status,guest_name,total_price,final_price,adults,children,total_guests,reference_number,booking_type,deleted_at,meal_quote_data',
                'room:id,name,max_guests',
                'roomUnit:id,unit_number',
            ])
            ->whereHas('booking', function ($q) use ($startDate, $endDate, $statuses, $isDayView) {
                if ($isDayView) {
                    // For day view: show bookings that are active on this specific day
                    // Include Day Tour bookings (same check-in and check-out date)
                    $q->where(function ($dayQuery) use ($startDate) {
                        $dayQuery->where(function ($overnightQuery) use ($startDate) {
                            // Overnight bookings: check_in_date <= selected_date AND check_out_date > selected_date
                            $overnightQuery->where('check_in_date', '<=', $startDate->toDateString())
                                          ->where('check_out_date', '>', $startDate->toDateString());
                        })->orWhere(function ($dayTourQuery) use ($startDate) {
                            // Day Tour bookings: check_in_date = selected_date AND check_out_date = selected_date
                            $dayTourQuery->where('check_in_date', '=', $startDate->toDateString())
                                        ->where('check_out_date', '=', $startDate->toDateString())
                                        ->where('booking_type', 'day_tour');
                        });
                    });
                } else {
                    // For month view: show all overlapping bookings
                    $q->where(function ($monthQuery) use ($startDate, $endDate) {
                        $monthQuery->where(function ($overnightQuery) use ($startDate, $endDate) {
                            // Overnight bookings: standard overlap logic (excluding checkout days)
                            $overnightQuery->where('check_in_date', '<=', $endDate->toDateString())
                                          ->where('check_out_date', '>', $startDate->toDateString());
                        })->orWhere(function ($dayTourQuery) use ($startDate, $endDate) {
                            // Day Tour bookings: check_in_date within the range
                            $dayTourQuery->where('booking_type', 'day_tour')
                                        ->where('check_in_date', '>=', $startDate->toDateString())
                                        ->where('check_in_date', '<=', $endDate->toDateString());
                        });
                    });
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
            
            // Determine meal program type based on meal_quote_data
            $mealProgramType = $this->determineMealProgramType($booking);
            
            // For Day Tours, price_per_night actually contains price_per_pax
            $isDayTour = $booking->booking_type === 'day_tour';
            
            $eventData = [
                'booking_id' => (int) $booking->id,
                'reference_number' => $booking->reference_number,
                'booking_type' => $booking->booking_type ?? 'overnight',
                'meal_program_type' => $mealProgramType,
                'meal_quote_data' => $booking->meal_quote_data,
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
            
            // Add Day Tour specific fields
            if ($isDayTour) {
                $eventData['price_per_pax'] = $br->price_per_night; // For Day Tours, this is actually price per pax
                $eventData['include_lunch'] = $br->include_lunch ?? false;
                $eventData['include_pm_snack'] = $br->include_pm_snack ?? false;
                $eventData['lunch_cost'] = $br->lunch_cost ?? 0;
                $eventData['pm_snack_cost'] = $br->pm_snack_cost ?? 0;
                $eventData['meal_cost'] = $br->meal_cost ?? 0;
                $eventData['base_price'] = $br->base_price ?? 0;
                $eventData['room_total_price'] = $br->total_price ?? 0;
            }
            
            $events[] = $eventData;
        }

        // Occupancy: count unique bookings per night (not individual rooms)
        $bookingRows = Booking::query()
            ->where(function($q) use ($startDate, $endDate, $isDayView) {
                if ($isDayView) {
                    // For day view: include both overnight and day tour bookings
                    $q->where(function ($dayQuery) use ($startDate) {
                        $dayQuery->where(function ($overnightQuery) use ($startDate) {
                            // Overnight bookings: check_in_date <= selected_date AND check_out_date > selected_date
                            $overnightQuery->where('check_in_date', '<=', $startDate->toDateString())
                                          ->where('check_out_date', '>', $startDate->toDateString());
                        })->orWhere(function ($dayTourQuery) use ($startDate) {
                            // Day Tour bookings: check_in_date = selected_date AND check_out_date = selected_date
                            $dayTourQuery->where('check_in_date', '=', $startDate->toDateString())
                                        ->where('check_out_date', '=', $startDate->toDateString())
                                        ->where('booking_type', 'day_tour');
                        });
                    });
                } else {
                    // For month view: include both overnight and day tour bookings
                    $q->where(function ($monthQuery) use ($startDate, $endDate) {
                        $monthQuery->where(function ($overnightQuery) use ($startDate, $endDate) {
                            // Overnight bookings: standard overlap logic (excluding checkout days)
                            $overnightQuery->where('check_in_date', '<=', $endDate->toDateString())
                                          ->where('check_out_date', '>', $startDate->toDateString());
                        })->orWhere(function ($dayTourQuery) use ($startDate, $endDate) {
                            // Day Tour bookings: check_in_date within the range
                            $dayTourQuery->where('booking_type', 'day_tour')
                                        ->where('check_in_date', '>=', $startDate->toDateString())
                                        ->where('check_in_date', '<=', $endDate->toDateString());
                        });
                    });
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

        // Build per-day summary with booking type breakdown
        $summaryMap = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $summaryMap[$cursor->toDateString()] = [
                'total' => 0,
                'overnight' => 0,
                'day_tour' => 0
            ];
            $cursor->addDay();
        }
        
        foreach ($bookingRows as $booking) {
            $s = Carbon::parse($booking->check_in_date);
            $e = Carbon::parse($booking->check_out_date);
            
            // For Day Tours, only count on the single date
            if ($booking->booking_type === 'day_tour') {
                $key = $s->toDateString();
                if (array_key_exists($key, $summaryMap)) {
                    $summaryMap[$key]['total'] += 1;
                    $summaryMap[$key]['day_tour'] += 1;
                }
            } else {
                // For overnight bookings, count each day (excluding checkout day)
                $startLoop = $s->max($startDate);
                $endLoop = $e->min($endDate);
                $day = $startLoop->copy();
                
                while ($day->lt($endLoop)) { // Use < instead of <= to exclude checkout day
                    $key = $day->toDateString();
                    if (array_key_exists($key, $summaryMap)) {
                        $summaryMap[$key]['total'] += 1;
                        $summaryMap[$key]['overnight'] += 1;
                    }
                    $day->addDay();
                }
            }
        }

        $summary = [];
        foreach ($summaryMap as $date => $counts) {
            $summary[] = [
                'date' => $date,
                'bookings' => (int) $counts['total'],
                'overnight_bookings' => (int) $counts['overnight'],
                'day_tour_bookings' => (int) $counts['day_tour'],
                'rooms_left' => max(0, $totalUnits - (int) $counts['total']),
            ];
        }

        return [
            'summary' => $summary,
            'events' => $events,
        ];
    }

    /**
     * Determine meal program type based on booking's meal_quote_data
     */
    private function determineMealProgramType(Booking $booking): string
    {
        $mealQuoteData = $booking->meal_quote_data;
        
        if (!$mealQuoteData || empty($mealQuoteData)) {
            return 'free_breakfast';
        }
        
        // Check if any night has buffet type
        if (isset($mealQuoteData['nights']) && is_array($mealQuoteData['nights'])) {
            foreach ($mealQuoteData['nights'] as $night) {
                if (isset($night['type']) && $night['type'] === 'buffet') {
                    return 'buffet';
                }
            }
        }
        
        return 'free_breakfast';
    }
}
