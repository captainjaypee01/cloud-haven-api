<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PaymentFailed extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $booking;
    public $payment;

    public function __construct($booking, $payment)
    {
        $this->booking = $booking;
        $this->payment = $payment;
    }

    public function envelope(): Envelope
    {
        $resortName = config('resort.name', config('app.name', 'Your Resort'));
        $bookingCode = $this->booking->reference_number ?? 'N/A';

        $subject = sprintf('Payment Failed — %s (%s)', $resortName, $bookingCode);

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
            messageId: sprintf('payment-failed-%s@%s', $uniqueId, $domain),
            text: [
                'X-Mailer' => 'Netania De Laiya Reservation System',
                'Precedence' => 'bulk',
                'List-Unsubscribe' => config('app.url', 'https://netaniadelaiya.com') . '/unsubscribe',
                'X-Auto-Response-Suppress' => 'All',
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment_failed',
            with: [
                'booking' => $this->booking,
                'payment' => $this->payment,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
