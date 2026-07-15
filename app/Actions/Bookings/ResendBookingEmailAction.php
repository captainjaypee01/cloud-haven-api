<?php

namespace App\Actions\Bookings;

use App\Mail\BookingConfirmation;
use App\Mail\BookingReservation;
use App\Models\Booking;
use App\Services\EmailTrackingService;

class ResendBookingEmailAction
{
    /**
     * @return array{email_type: string, recipient: string, message: string}
     */
    public function execute(Booking $booking, string $emailType): array
    {
        if (blank($booking->guest_email)) {
            throw new \InvalidArgumentException('This booking has no guest email address.');
        }

        if ($booking->status === 'cancelled') {
            throw new \InvalidArgumentException('Cannot resend booking emails for cancelled bookings.');
        }

        $booking->loadMissing(['bookingRooms.room', 'payments', 'otherCharges']);

        $mailable = match ($emailType) {
            'reservation' => new BookingReservation($booking),
            'confirmation' => new BookingConfirmation($booking),
            default => throw new \InvalidArgumentException('Invalid email type. Use reservation or confirmation.'),
        };

        $trackingType = $emailType === 'reservation' ? 'booking_reservation' : 'booking_confirmation';

        EmailTrackingService::sendWithTracking(
            $booking->guest_email,
            $mailable,
            $trackingType,
            [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'guest_name' => $booking->guest_name,
                'resent_by_admin' => true,
            ]
        );

        $label = $emailType === 'reservation' ? 'Booking Reservation' : 'Booking Confirmation';

        return [
            'email_type' => $emailType,
            'recipient' => $booking->guest_email,
            'message' => "{$label} email queued to {$booking->guest_email}.",
        ];
    }
}
