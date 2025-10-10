<?php

namespace App\Console\Commands;

use App\Mail\ReviewRequestMail;
use App\Models\Booking;
use App\Services\EmailTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendReviewRequestEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reviews:send-request-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send review request emails to guests who checked out 1 day ago';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting review request email process...');

        // Find bookings that checked out 1 day ago and are eligible for review
        $yesterday = now()->subDay()->toDateString();
        
        $bookings = Booking::with(['bookingRooms.room'])
            ->where('check_out_date', $yesterday)
            ->whereIn('status', ['paid', 'downpayment'])
            ->where('is_reviewed', false)
            ->whereNull('review_email_sent_at')
            ->whereNotNull('guest_email')
            ->get();

        $this->info("Found {$bookings->count()} bookings eligible for review requests.");

        $sentCount = 0;
        $failedCount = 0;

        foreach ($bookings as $booking) {
            try {
                // Generate review token if not exists
                if (!$booking->review_token) {
                    $booking->generateReviewToken();
                }

                // Create review URL pointing to frontend
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                $reviewUrl = $frontendUrl . '/review/' . $booking->review_token;

                // Send email
                EmailTrackingService::sendWithTracking(
                    $booking->guest_email,
                    new ReviewRequestMail($booking, $reviewUrl),
                    'review_request',
                    [
                        'booking_id' => $booking->id,
                        'booking_reference' => $booking->reference_number,
                        'guest_name' => $booking->guest_name,
                        'check_out_date' => $booking->check_out_date->toDateString(),
                    ]
                );

                // Mark email as sent
                $booking->markReviewEmailAsSent();

                $sentCount++;
                $this->line("✓ Sent review request to {$booking->guest_name} ({$booking->guest_email}) - Booking #{$booking->reference_number}");

            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ Failed to send review request to {$booking->guest_name} ({$booking->guest_email}) - Booking #{$booking->reference_number}: {$e->getMessage()}");
                
                Log::error('Review request email failed', [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->reference_number,
                    'guest_email' => $booking->guest_email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Review request email process completed:");
        $this->info("- Emails sent: {$sentCount}");
        $this->info("- Failed: {$failedCount}");

        return Command::SUCCESS;
    }
}
