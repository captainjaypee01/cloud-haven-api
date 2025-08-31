<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProofPaymentAcceptedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Payment $payment;
    public $booking;

    /**
     * Create a new message instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment->load('booking');
        $this->booking = $payment->booking;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $resortName = config('resort.name', config('app.name', 'Your Resort'));
        $bookingCode = $this->booking->reference_number ?? 'N/A';

        $subject = sprintf('[Payment Confirmed] %s — %s', $bookingCode, $resortName);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment_success',
            with: [
                'payment' => $this->payment,
                'booking' => $this->booking,
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
