<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\Promo;
use App\Services\CacheInvalidationService;
use App\Services\Bookings\BookingRoomUnitReassignmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdjustBookingNightsAction
{
    public function __construct(
        private CheckRoomAvailabilityAction $checkAvailability,
        private CalculateBookingTotalAction $calculateBookingTotal,
        private BookingRoomUnitReassignmentService $roomUnitReassignment,
        private CacheInvalidationService $cacheInvalidation,
        private PersistBookingRoomNightlyRatesAction $persistNightlyRates,
        private SyncBookingRoomLineTotalsFromQuoteAction $syncRoomLineTotals,
        private SyncBookingDownpaymentAction $syncDownpayment,
    ) {}

    public function execute(
        Booking $booking,
        string $newCheckOutDate,
        ?string $modificationReason = null,
    ): Booking {
        $booking->load('bookingRooms.room');

        $oldCheckOut = $booking->check_out_date;
        $checkIn = $booking->check_in_date;

        if ($booking->booking_type === 'day_tour') {
            throw new \InvalidArgumentException('Adjust nights is only for overnight bookings.');
        }

        $newCheckOut = Carbon::parse($newCheckOutDate)->format('Y-m-d');
        $checkInCarbon = Carbon::parse($checkIn);

        if (Carbon::parse($newCheckOut)->lte($checkInCarbon)) {
            throw new \InvalidArgumentException('Check-out date must be after check-in date.');
        }

        $bookingRoomArr = [];
        foreach ($booking->bookingRooms as $br) {
            $slug = $br->room?->slug;
            if (! $slug) {
                continue;
            }
            $bookingRoomArr[] = (object) [
                'room_id' => $slug,
                'adults' => $br->adults,
                'children' => $br->children,
            ];
        }

        if ($bookingRoomArr === []) {
            throw new \InvalidArgumentException('Booking has no rooms to price.');
        }

        $this->checkAvailability->execute(
            $bookingRoomArr,
            $checkIn,
            $newCheckOut,
            $booking->id
        );

        $promo = $booking->promo_id ? Promo::find($booking->promo_id) : null;

        $totals = $this->calculateBookingTotal->execute(
            $bookingRoomArr,
            $checkIn,
            $newCheckOut,
            (int) $booking->adults,
            (int) $booking->children,
            $promo,
            $booking,
        );

        $discountAmount = isset($totals['promo_discount']) ? ($totals['promo_discount']['discount_amount'] ?? 0) : 0;

        return DB::transaction(function () use (
            $booking,
            $checkIn,
            $newCheckOut,
            $oldCheckOut,
            $totals,
            $discountAmount,
            $modificationReason,
        ) {
            $mealQuote = $totals['meal_quote'];
            $roomQuote = $totals['room_quote'] ?? null;

            $updatePayload = [
                'check_out_date' => $newCheckOut,
                'total_price' => $totals['total_room'],
                'meal_price' => $totals['meal_total'],
                'extra_guest_fee' => $totals['extra_guest_fee'],
                'extra_guest_count' => $totals['extra_guest_count'],
                'final_price' => $totals['final_price'],
                'discount_amount' => $discountAmount,
                'downpayment_amount' => $this->syncDownpayment->calculateFromTotals($totals),
                'meal_quote_data' => $mealQuote ? json_encode($mealQuote->toArray()) : null,
                'room_quote_data' => $roomQuote ? $roomQuote->toArray() : null,
            ];
            if ($modificationReason !== null && $modificationReason !== '') {
                $updatePayload['modification_reason'] = $modificationReason;
            }

            $booking->update($updatePayload);

            $booking->refresh();
            $booking->load('bookingRooms.room');

            if ($roomQuote) {
                $this->persistNightlyRates->execute($booking, $roomQuote);
                $this->syncRoomLineTotals->execute($booking, $roomQuote, $checkIn, $newCheckOut);
            }

            $this->roomUnitReassignment->reassignRoomUnitsForBooking($booking, $checkIn, $newCheckOut);

            Log::info('Booking nights adjusted', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'admin_user_id' => Auth::id(),
                'old_check_out' => $oldCheckOut,
                'new_check_out' => $newCheckOut,
                'total_room' => $totals['total_room'],
                'meal_total' => $totals['meal_total'],
                'final_price' => $totals['final_price'],
            ]);

            $this->cacheInvalidation->clearCacheForDateRange($checkIn, $oldCheckOut);
            $this->cacheInvalidation->clearCacheForDateRange($checkIn, $newCheckOut);

            return $booking->refresh();
        });
    }
}
