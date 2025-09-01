<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $booking, $downpayment;
    public $payment_method; // optional; pass if you have it
    /**
     * Create a new message instance.
     */
    public function __construct($booking, $downpayment, $payment_method = null)
    {
        $this->booking = $booking;
        $this->downpayment = $downpayment;
        $this->payment_method = $payment_method;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $resortName = config('resort.name', config('app.name', 'Your Resort'));
        $bookingCode = $this->booking->reference_number ?? 'N/A';

        $subject = sprintf('[Booking Confirmed] — %s (%s)', $resortName, $bookingCode);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        // Load room unit data for the booking
        $this->booking->load('bookingRooms.room', 'bookingRooms.roomUnit');
        
        return new Content(
            markdown: 'emails.booking_confirmation',
            with: [
                'booking' => $this->booking,
                'downpayment' => $this->downpayment,
                'payment_method' => $this->payment_method,
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
