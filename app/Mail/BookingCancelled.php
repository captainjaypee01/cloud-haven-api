<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingCancelled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $booking;
    public $reason;
    public $isManualCancellation;

    /**
     * Create a new message instance.
     */
    public function __construct($booking, ?string $reason = null, bool $isManualCancellation = false)
    {
        $this->booking = $booking;
        $this->reason = $reason;
        $this->isManualCancellation = $isManualCancellation;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $resortName = config('resort.name', config('app.name', 'Your Resort'));
        $bookingCode = $this->booking->reference_number ?? 'N/A';

        $subject = $this->isManualCancellation 
            ? sprintf('❌ Booking Cancelled by Admin - %s (%s)', $resortName, $bookingCode)
            : sprintf('❌ Booking Cancelled - No Proof of Payment - %s (%s)', $resortName, $bookingCode);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking_cancelled',
            with: [
                'booking' => $this->booking,
                'reason' => $this->reason,
                'isManualCancellation' => $this->isManualCancellation,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
