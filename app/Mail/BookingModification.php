<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingModification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public ?string $modificationReason = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Booking Modification - {$this->booking->reference_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking_modification',
            with: [
                'booking' => $this->booking,
                'modificationReason' => $this->modificationReason,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
