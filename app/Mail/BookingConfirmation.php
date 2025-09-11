<?php

namespace App\Mail;

use App\Services\ResortPoliciesPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BookingConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $booking;

    /**
     * Create a new message instance.
     */
    public function __construct($booking)
    {
        $this->booking = $booking;
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
        return new Content(
            markdown: 'emails.booking_confirmation',
            with: ['booking' => $this->booking],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        try {
            $pdfService = app(ResortPoliciesPdfService::class);
            $pdfPath = $pdfService->generatePdf();
            $filename = $pdfService->getFilename();
            
            return [
                Attachment::fromPath($pdfPath)
                    ->as($filename)
                    ->withMime('application/pdf'),
            ];
        } catch (\Exception $e) {
            // Log the error but don't fail the email
            Log::error('Failed to generate policies PDF for confirmation email: ' . $e->getMessage());
            return [];
        }
    }
}