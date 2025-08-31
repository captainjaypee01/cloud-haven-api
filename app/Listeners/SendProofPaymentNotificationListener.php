<?php

namespace App\Listeners;

use App\Events\PaymentProofUploaded;
use App\Mail\ProofPaymentUploadedMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

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
                Log::info("Payment proof notification suppressed for payment {$payment->id} - too soon since last notification");
                return;
            }
        }

        try {
            // Queue the email
            Mail::to($notificationEmail)->queue(
                new ProofPaymentUploadedMail(
                    $event->payment,
                    $event->booking,
                    $event->sequenceNumber,
                    $event->adminLink
                )
            );

            // Update the last notification timestamp
            $payment->update(['last_proof_notification_at' => now()]);
            
            Log::info("Payment proof notification queued for payment {$payment->id}");
        } catch (\Exception $e) {
            Log::error("Failed to queue payment proof notification for payment {$payment->id}: " . $e->getMessage());
        }
    }
}
