<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Contracts\Services\BookingLockServiceInterface;
use App\Mail\BookingCancelled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingCancellationService
{
    public function __construct(
        private BookingLockServiceInterface $lockService
    ) {}

    /**
     * Manually cancel a booking from admin panel
     */
    public function cancelBooking(Booking $booking, string $reason, int $adminUserId): array
    {
        // Check if booking can be cancelled
        if (!$this->canCancel($booking)) {
            return [
                'success' => false,
                'error_code' => 'cannot_cancel',
                'message' => 'This booking cannot be cancelled.'
            ];
        }

        return DB::transaction(function () use ($booking, $reason, $adminUserId) {
            try {
                // Remove Redis lock if exists
                $this->lockService->delete($booking->id);

                // Update booking status
                $booking->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $adminUserId,
                    'cancellation_reason' => $reason,
                ]);

                // Send cancellation email
                Mail::to($booking->guest_email)->queue(new BookingCancelled($booking, $reason, true));

                // Log the action
                Log::info("Booking manually cancelled by admin", [
                    'booking_id' => $booking->id,
                    'reference_number' => $booking->reference_number,
                    'admin_user_id' => $adminUserId,
                    'reason' => $reason,
                ]);

                return [
                    'success' => true,
                    'message' => 'Booking cancelled successfully.',
                    'booking' => $booking->fresh()
                ];

            } catch (\Exception $e) {
                Log::error("Failed to cancel booking {$booking->id}: " . $e->getMessage());
                
                return [
                    'success' => false,
                    'error_code' => 'cancellation_failed',
                    'message' => 'Failed to cancel booking. Please try again.'
                ];
            }
        });
    }

    /**
     * Check if a booking can be cancelled
     */
    public function canCancel(Booking $booking): bool
    {
        // Cannot cancel if already cancelled
        if ($booking->status === 'cancelled') {
            return false;
        }

        // Cannot cancel if fully paid and confirmed
        if ($booking->status === 'paid') {
            return false;
        }

        // Cannot cancel if downpayment is made and confirmed
        if ($booking->status === 'downpayment') {
            return false;
        }

        // Can cancel if pending or failed
        return in_array($booking->status, ['pending', 'failed']);
    }

    /**
     * Get cancellation reasons for admin selection
     */
    public function getCancellationReasons(): array
    {
        $allReasons = config('booking.cancellation_reasons', []);
        
        // Return only manual admin cancellation reasons (exclude system automatic ones)
        return array_filter($allReasons, function($key) {
            return !in_array($key, ['no_payment_received', 'rejected_proof_expired']);
        }, ARRAY_FILTER_USE_KEY);
    }
    
    /**
     * Get a specific cancellation reason text by key
     */
    public function getCancellationReason(string $key): string
    {
        return config("booking.cancellation_reasons.{$key}", $key);
    }
    
    /**
     * Get system automatic cancellation reasons
     */
    public function getSystemCancellationReasons(): array
    {
        return [
            'no_payment_received' => config('booking.cancellation_reasons.no_payment_received'),
            'rejected_proof_expired' => config('booking.cancellation_reasons.rejected_proof_expired'),
        ];
    }
}
