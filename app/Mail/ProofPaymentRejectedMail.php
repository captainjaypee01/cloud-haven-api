<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProofPaymentRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Payment $payment;
    public $booking;
    public string $rejectionReason;

    /**
     * Create a new message instance.
     */
    public function __construct(Payment $payment, string $rejectionReason = '')
    {
        $this->payment = $payment->load('booking');
        $this->booking = $payment->booking;
        $this->rejectionReason = $rejectionReason ?: 'Payment verification could not be completed at this time.';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $resortName = config('resort.name', config('app.name', 'Your Resort'));
        $bookingCode = $this->booking->reference_number ?? 'N/A';
        $paymentId = $this->payment->id;

        $subject = sprintf('❌ Payment Rejected - #%s — %s (%s)', $paymentId, $resortName, $bookingCode);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment_problem',
            with: [
                'payment' => $this->payment,
                'booking' => $this->booking,
                'rejectionReason' => $this->rejectionReason,
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
