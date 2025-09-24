<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingReschedule extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $oldCheckIn,
        public string $oldCheckOut
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking Rescheduled - ' . $this->booking->reference_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking_reschedule',
            with: [
                'booking' => $this->booking,
                'oldCheckIn' => $this->oldCheckIn,
                'oldCheckOut' => $this->oldCheckOut,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
