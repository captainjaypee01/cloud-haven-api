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

class BookingReschedule extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $booking;
    public $oldCheckIn;
    public $oldCheckOut;

    /**
     * Create a new message instance.
     */
    public function __construct($booking, string $oldCheckIn, string $oldCheckOut)
    {
        $this->booking = $booking;
        $this->oldCheckIn = $oldCheckIn;
        $this->oldCheckOut = $oldCheckOut;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $resortName = config('resort.name', config('app.name', 'Your Resort'));
        $bookingCode = $this->booking->reference_number ?? 'N/A';

        $subject = sprintf('[Booking Rescheduled] — %s (%s)', $resortName, $bookingCode);

        return new Envelope(subject: $subject);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.booking_reschedule',
            with: [
                'booking' => $this->booking,
                'oldCheckIn' => $this->oldCheckIn,
                'oldCheckOut' => $this->oldCheckOut,
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
            Log::error('Failed to generate policies PDF for reschedule email: ' . $e->getMessage());
            return [];
        }
    }
}
