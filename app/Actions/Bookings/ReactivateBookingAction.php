<?php

namespace App\Actions\Bookings;

use App\Contracts\Services\BookingLockServiceInterface;
use App\Models\Booking;
use App\Services\Bookings\BookingRoomUnitReassignmentService;
use App\Services\CacheInvalidationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReactivateBookingAction
{
    public function __construct(
        private CheckRoomAvailabilityAction $checkAvailability,
        private BookingRoomUnitReassignmentService $roomUnitReassignment,
        private BookingLockServiceInterface $lockService,
        private CacheInvalidationService $cacheInvalidation,
    ) {}

    /**
     * Returns why the booking cannot be reactivated/extended, or null if it can.
     *
     * Eligible bookings:
     * - 'pending' bookings (extend the hold before or after reserved_until passes)
     * - 'cancelled' bookings whose cancellation was the automatic hold expiry
     */
    public function ineligibilityReason(Booking $booking): ?string
    {
        if ($booking->trashed()) {
            return 'Deleted bookings cannot be reactivated.';
        }

        if ($booking->status === 'cancelled') {
            if (! in_array($booking->cancellation_reason, $this->reactivatableReasonTexts(), true)) {
                return 'Only bookings cancelled automatically due to an expired hold can be reactivated.';
            }
        } elseif ($booking->status !== 'pending') {
            return 'Only pending or expired bookings can be reactivated.';
        }

        $today = now('Asia/Singapore')->toDateString();
        if (Carbon::parse($booking->check_in_date)->toDateString() < $today) {
            return 'The check-in date has already passed. Please create a new booking instead.';
        }

        return null;
    }

    /**
     * Restore an expired booking to 'pending' (or extend a pending one) with a fresh hold.
     *
     * Room availability is re-checked against the original dates because an expired
     * hold no longer reserves its units; units taken in the meantime are swapped for
     * another free unit of the same room type.
     *
     * @throws \InvalidArgumentException when the booking is not eligible
     * @throws \App\Exceptions\RoomNotAvailableException when the rooms are no longer available
     */
    public function execute(Booking $booking, int $holdHours, int $adminUserId): Booking
    {
        return DB::transaction(function () use ($booking, $holdHours, $adminUserId) {
            // Lock the row so two admins clicking at once cannot both reactivate
            $booking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($reason = $this->ineligibilityReason($booking)) {
                throw new \InvalidArgumentException($reason);
            }

            $previousStatus = $booking->status;
            $previousReason = $booking->cancellation_reason;
            $checkIn = Carbon::parse($booking->check_in_date)->format('Y-m-d');
            $checkOut = Carbon::parse($booking->check_out_date)->format('Y-m-d');

            $booking->load('bookingRooms.room');

            $bookingRoomArr = $booking->bookingRooms->map(fn ($br) => (object) [
                'room_id' => $br->room->slug,
                'adults' => $br->adults,
                'children' => $br->children,
            ])->all();

            $this->checkAvailability->execute($bookingRoomArr, $checkIn, $checkOut, $booking->id);
            $this->roomUnitReassignment->reassignRoomUnitsForBooking($booking, $checkIn, $checkOut);

            $reservedUntil = now()->addHours($holdHours);

            $booking->update([
                'status' => 'pending',
                'reserved_until' => $reservedUntil,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
            ]);

            $this->lockService->lock($booking->id, [
                'rooms' => array_map(fn ($r) => (array) $r, $bookingRoomArr),
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'expires_at' => $reservedUntil->timestamp,
            ]);

            $this->cacheInvalidation->clearCacheForDateRange($checkIn, $checkOut);

            Log::info('Booking hold reactivated by admin', [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'admin_user_id' => $adminUserId,
                'previous_status' => $previousStatus,
                'previous_cancellation_reason' => $previousReason,
                'hold_hours' => $holdHours,
                'reserved_until' => $reservedUntil->toDateTimeString(),
            ]);

            return $booking->fresh(['bookingRooms.room', 'bookingRooms.roomUnit', 'payments']);
        });
    }

    /**
     * Cancellation reasons are stored as text, so compare against the configured texts.
     *
     * @return array<int, string>
     */
    private function reactivatableReasonTexts(): array
    {
        return collect(config('booking.reactivatable_cancellation_reasons', []))
            ->map(fn ($key) => config("booking.cancellation_reasons.{$key}"))
            ->filter()
            ->values()
            ->all();
    }
}
