<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $booking;
    public $reviewUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($booking, string $reviewUrl)
    {
        $this->booking = $booking;
        $this->reviewUrl = $reviewUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $resortName = config('resort.name', config('app.name', 'Netania De Laiya'));
        $bookingCode = $this->booking->reference_number ?? 'N/A';

        $subject = sprintf('How was your stay at %s? - Booking %s', $resortName, $bookingCode);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.review_request',
            with: [
                'booking' => $this->booking,
                'reviewUrl' => $this->reviewUrl,
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
