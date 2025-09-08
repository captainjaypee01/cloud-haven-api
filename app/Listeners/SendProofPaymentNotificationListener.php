<?php

namespace App\Listeners;

use App\Events\PaymentProofUploaded;
use App\Mail\ProofPaymentUploadedMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\EmailTrackingService;

class SendProofPaymentNotificationListener
{
    /**
     * Handle the event.
     */
    public function handle(PaymentProofUploaded $event): void
    {
        $payment = $event->payment;
        $suppressWindowMinutes = config('notifications.proof_payment.suppress_window_minutes', 5);
        $notificationEmail = config('notifications.proof_payment.to', 'proof@netaniadelaiya.com');

        // Check if we should suppress this notification based on last notification time
        if ($payment->last_proof_notification_at) {
            $lastNotificationTime = Carbon::parse($payment->last_proof_notification_at);
            $timeSinceLastNotification = $lastNotificationTime->diffInMinutes(now());
            
            if ($timeSinceLastNotification < $suppressWindowMinutes) {
                Log::info("Payment proof notification suppressed for payment {$payment->id} - too soon since last notification", [
                    'payment_id' => $payment->id,
                    'booking_id' => $event->booking->id,
                    'booking_reference' => $event->booking->reference_number,
                    'time_since_last_notification' => $timeSinceLastNotification,
                    'suppress_window_minutes' => $suppressWindowMinutes,
                    'last_notification_at' => $payment->last_proof_notification_at
                ]);
                return;
            }
        }

        try {
            // Queue the email with comprehensive tracking
            EmailTrackingService::sendWithTracking(
                $notificationEmail,
                new ProofPaymentUploadedMail(
                    $event->payment,
                    $event->booking,
                    $event->sequenceNumber,
                    $event->adminLink
                ),
                'proof_payment_uploaded_admin',
                [
                    'payment_id' => $payment->id,
                    'booking_id' => $event->booking->id,
                    'booking_reference' => $event->booking->reference_number,
                    'sequence_number' => $event->sequenceNumber,
                    'upload_count' => $payment->proof_upload_count,
                    'guest_email' => $event->booking->guest_email,
                    'admin_link' => $event->adminLink
                ]
            );

            // Update the last notification timestamp
            $payment->update(['last_proof_notification_at' => now()]);
            
        } catch (\Exception $e) {
            Log::error("Failed to queue payment proof notification for payment {$payment->id}", [
                'payment_id' => $payment->id,
                'booking_id' => $event->booking->id,
                'booking_reference' => $event->booking->reference_number,
                'error' => $e->getMessage()
            ]);
        }
    }
}
