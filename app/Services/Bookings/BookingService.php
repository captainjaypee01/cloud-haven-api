<?php

namespace App\Services\Bookings;

use App\Actions\Bookings\CalculateBookingTotalAction;
use App\Actions\Bookings\CheckRoomAvailabilityAction;
use App\Actions\Bookings\CreateBookingEntitiesAction;
use App\Actions\Bookings\SetBookingLockAction;
use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Services\BookingServiceInterface;
use App\Dto\Bookings\BookingData;
use App\DTO\Bookings\BookingRoomData;
use App\Models\Booking;
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
        Log::info([$paidAmount, $dpAmount, $finalPrice, $paidAmount >= $dpAmount, $paidAmount >= $finalPrice]);
        if ($paidAmount >= $finalPrice) {
            $booking->update(['status' => 'paid', 'paid_at' => now()]);
        } elseif ($paidAmount >= $dpAmount) {
            $booking->update(['status' => 'downpayment', 'downpayment_at' => now()]);
        } else {
            $booking->update(['status' => 'pending']);
        }
    }

    public function markPaymentFailed(Booking $booking): void
    {
        $booking->increment('failed_payment_attempts');
        $booking->update(['last_payment_failed_at' => now()]);
    }
}
