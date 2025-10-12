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

class BookingModification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $booking;
    public $modificationReason;

    /**
     * Create a new message instance.
     */
    public function __construct($booking, ?string $modificationReason = null)
    {
        $this->booking = $booking;
        $this->modificationReason = $modificationReason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $resortName = config('resort.name', config('app.name', 'Your Resort'));
        $bookingCode = $this->booking->reference_number ?? 'N/A';

        $subject = sprintf('[Booking Modified] — %s (%s)', $resortName, $bookingCode);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
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

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        try {
            $pdfService = app(ResortPoliciesPdfService::class);
            $pdfPath = $pdfService->generatePdf($this->booking);
            $filename = $pdfService->getFilename($this->booking);
            
            return [
                Attachment::fromPath($pdfPath)
                    ->as($filename)
                    ->withMime('application/pdf'),
            ];
        } catch (\Exception $e) {
            // Log the error but don't fail the email
            Log::error('Failed to generate policies PDF for modification email: ' . $e->getMessage());
            return [];
        }
    }
}
