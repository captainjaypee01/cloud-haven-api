<?php

namespace App\Services;

use App\Mail\ReviewRequestMail;
use App\Models\Booking;
use App\Services\EmailTrackingService;
use Illuminate\Support\Facades\Log;

class ReviewService
{
    /**
     * Generate a review token for a booking
     */
    public function generateReviewToken(Booking $booking): string
    {
        return $booking->generateReviewToken();
    }

    /**
     * Send review request email to guest
     */
    public function sendReviewRequestEmail(Booking $booking): bool
    {
        try {
            // Generate token if not exists
            if (!$booking->review_token) {
                $this->generateReviewToken($booking);
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
                    'check_out_date' => is_string($booking->check_out_date) ? $booking->check_out_date : $booking->check_out_date->toDateString(),
                ]
            );

            // Mark email as sent
            $booking->markReviewEmailAsSent();

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send review request email', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'guest_email' => $booking->guest_email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get bookings eligible for review requests
     */
    public function getEligibleBookingsForReviewRequest(int $daysAfterCheckout = 1): \Illuminate\Database\Eloquent\Collection
    {
        $targetDate = now()->subDays($daysAfterCheckout)->toDateString();
        
        return Booking::with(['bookingRooms.room'])
            ->where('check_out_date', $targetDate)
            ->whereIn('status', ['paid', 'downpayment'])
            ->where('is_reviewed', false)
            ->whereNull('review_email_sent_at')
            ->whereNotNull('guest_email')
            ->get();
    }

    /**
     * Validate review token and get booking
     */
    public function validateReviewToken(string $token): ?Booking
    {
        $booking = Booking::with(['bookingRooms.room'])
            ->where('review_token', $token)
            ->first();

        if (!$booking || !$booking->isReviewTokenValid($token)) {
            return null;
        }

        if (!$booking->isEligibleForReview()) {
            return null;
        }

        return $booking;
    }

    /**
     * Check if booking can receive review request
     */
    public function canSendReviewRequest(Booking $booking): bool
    {
        return $booking->isEligibleForReview() 
            && !$booking->hasReviewEmailBeenSent()
            && !is_null($booking->guest_email);
    }
}