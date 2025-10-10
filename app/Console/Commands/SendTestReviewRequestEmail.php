<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\ReviewService;
use Illuminate\Console\Command;

class SendTestReviewRequestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reviews:send-test-email {booking_reference}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test review request email for a specific booking';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bookingReference = $this->argument('booking_reference');
        
        $booking = Booking::where('reference_number', $bookingReference)->first();
        
        if (!$booking) {
            $this->error("Booking with reference '{$bookingReference}' not found.");
            return Command::FAILURE;
        }

        $this->info("Found booking: {$booking->reference_number}");
        $this->info("Guest: {$booking->guest_name} ({$booking->guest_email})");
        $this->info("Check-out: {$booking->check_out_date}");

        if (!$booking->guest_email) {
            $this->error("No guest email found for this booking.");
            return Command::FAILURE;
        }

        $reviewService = app(ReviewService::class);
        
        if ($reviewService->sendReviewRequestEmail($booking)) {
            $this->info("✅ Review request email sent successfully!");
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $this->info("Review URL: " . $frontendUrl . "/review/{$booking->review_token}");
        } else {
            $this->error("❌ Failed to send review request email.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
