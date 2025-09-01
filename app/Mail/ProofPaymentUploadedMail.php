<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProofPaymentUploadedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Payment $payment;
    public Booking $booking;
    public int $sequenceNumber;
    public string $adminLink;

    /**
     * Create a new message instance.
     */
    public function __construct(Payment $payment, Booking $booking, int $sequenceNumber, string $adminLink)
    {
        $this->payment = $payment;
        $this->booking = $booking;
        $this->sequenceNumber = $sequenceNumber;
        $this->adminLink = $adminLink;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $resortName = config('resort.name', config('app.name', 'Your Resort'));
        $bookingCode = $this->booking->reference_number ?? 'N/A';
        $paymentId = $this->payment->id;

        $subject = sprintf('📤 Proof Uploaded - #%s — %s (%s)', $paymentId, $resortName, $bookingCode);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.proof_payment_uploaded',
            with: [
                'payment' => $this->payment,
                'booking' => $this->booking,
                'sequenceNumber' => $this->sequenceNumber,
                'adminLink' => $this->adminLink,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
