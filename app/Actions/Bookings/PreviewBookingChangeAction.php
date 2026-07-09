<?php

namespace App\Actions\Bookings;

use App\Models\Booking;
use App\Models\Promo;
use App\Services\Bookings\BookingBalanceService;
use InvalidArgumentException;

class PreviewBookingChangeAction
{
    public function __construct(
        private readonly CalculateBookingTotalAction $calculateBookingTotal,
        private readonly BookingBalanceService $bookingBalance,
    ) {}

    public function execute(Booking $booking, string $changeType, array $params): array
    {
        $booking->load('bookingRooms.room', 'payments', 'otherCharges');

        $totals = match ($changeType) {
            'adjust_nights' => $this->totalsForAdjustNights($booking, $params['new_check_out_date']),
            'reschedule' => $this->totalsForReschedule(
                $booking,
                $params['check_in_date'],
                $params['check_out_date']
            ),
            'modify' => $this->totalsForModify($booking, $params['rooms']),
            default => throw new InvalidArgumentException("Unsupported change type: {$changeType}"),
        };

        return $this->bookingBalance->compareCurrentAndProposed($booking, $totals);
    }

    private function totalsForAdjustNights(Booking $booking, string $newCheckOut): array
    {
        return $this->calculateBookingTotal->execute(
            $this->bookingRoomArrFromBooking($booking),
            $booking->check_in_date,
            $newCheckOut,
            (int) $booking->adults,
            (int) $booking->children,
            $booking->promo_id ? Promo::find($booking->promo_id) : null,
            $booking,
        );
    }

    private function totalsForReschedule(Booking $booking, string $checkIn, string $checkOut): array
    {
        return $this->calculateBookingTotal->execute(
            $this->bookingRoomArrFromBooking($booking),
            $checkIn,
            $checkOut,
            (int) $booking->adults,
            (int) $booking->children,
            $booking->promo_id ? Promo::find($booking->promo_id) : null,
            $booking,
        );
    }

    private function totalsForModify(Booking $booking, array $rooms): array
    {
        $bookingRoomArr = array_map(fn ($room) => (object) $room, $rooms);

        return $this->calculateBookingTotal->execute(
            $bookingRoomArr,
            $booking->check_in_date,
            $booking->check_out_date,
            (int) array_sum(array_column($rooms, 'adults')),
            (int) array_sum(array_column($rooms, 'children')),
            $booking->promo_id ? Promo::find($booking->promo_id) : null,
            $booking,
        );
    }

    private function bookingRoomArrFromBooking(Booking $booking): array
    {
        return $booking->bookingRooms
            ->map(fn ($br) => (object) [
                'room_id' => $br->room?->slug,
                'adults' => $br->adults,
                'children' => $br->children,
            ])
            ->filter(fn ($row) => ! empty($row->room_id))
            ->values()
            ->all();
    }
}
