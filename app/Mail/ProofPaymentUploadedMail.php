<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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

        $subject = sprintf('Proof of Payment Uploaded — %s (%s)', $resortName, $bookingCode);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message headers.
     */
    public function headers(): Headers
    {
        $appUrl = config('app.url', 'https://netaniadelaiya.com');
        $domain = parse_url($appUrl, PHP_URL_HOST) ?: 'netaniadelaiya.com';
        $uniqueId = Str::uuid()->toString();
        
        return new Headers(
            messageId: sprintf('proof-uploaded-%s@%s', $uniqueId, $domain),
            text: [
                'X-Mailer' => 'Netania De Laiya Reservation System',
                'Precedence' => 'bulk',
                'List-Unsubscribe' => config('app.url', 'https://netaniadelaiya.com') . '/unsubscribe',
                'X-Auto-Response-Suppress' => 'All',
            ],
        );
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
