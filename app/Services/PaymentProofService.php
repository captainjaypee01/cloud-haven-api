<?php

namespace App\Services;

use App\Events\PaymentProofUploaded;
use App\Models\Payment;
use App\Models\Booking;
use App\Mail\ProofPaymentAcceptedMail;
use App\Mail\ProofPaymentRejectedMail;
use App\Services\Bookings\BookingCancellationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailTrackingService;
use Illuminate\Support\Str;

class PaymentProofService
{
    public function __construct(
        private BookingCancellationService $cancellationService
    ) {}

    /**
     * Upload proof of payment for a specific payment
     */
    public function uploadProof(Payment $payment, UploadedFile $file, ?string $transactionId = null, ?string $remarks = null): array
    {
        $maxUploads = config('notifications.proof_payment.max_uploads', 3);
        
        // Check if upload limit reached for current generation
        if ($payment->proof_upload_count >= $maxUploads) {
            return [
                'success' => false,
                'error_code' => 'proof_upload_limit_reached',
                'message' => "Maximum proof-of-payment uploads reached ({$maxUploads}) for this payment."
            ];
        }

        return DB::transaction(function () use ($payment, $file, $transactionId, $remarks) {
            try {
                // Delete previous proof file if exists
                if ($payment->proof_last_file_path) {
                    $this->deleteProofFile($payment->proof_last_file_path);
                }

                // Upload new file to local storage (same approach as existing PaymentController)
                $booking = $payment->booking ?? $payment->load('booking')->booking;
                $referenceNumber = $booking->reference_number;
                $filePath = $this->storeProofFile($file, $referenceNumber, $payment->id);

                if (!$filePath) {
                    throw new \Exception('Failed to store proof file');
                }

                // Update payment with new proof details and optional transaction data
                $updateData = [
                    'proof_last_file_path' => $filePath,
                    'proof_status' => 'pending',
                    'proof_upload_count' => $payment->proof_upload_count + 1,
                    'proof_last_uploaded_at' => now(),
                ];

                // Update transaction_id and remarks if provided
                if ($transactionId !== null) {
                    $updateData['transaction_id'] = $transactionId;
                }
                if ($remarks !== null) {
                    $updateData['remarks'] = $remarks;
                }

                $payment->update($updateData);

                // Load booking relationship
                $payment->load('booking');
                $booking = $payment->booking;

                // Generate admin link for reviewing the payment
                $adminLink = config('app.frontend_url') . '/admin/bookings/' . $booking->id;

                // Fire the event for email notification
                PaymentProofUploaded::dispatch(
                    $payment,
                    $booking,
                    $payment->proof_upload_count,
                    $adminLink
                );

                Log::info("Payment proof uploaded successfully", [
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id,
                    'reference_number' => $booking->reference_number,
                    'file_path' => $filePath,
                    'upload_count' => $payment->proof_upload_count,
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType()
                ]);

                return [
                    'success' => true,
                    'payment' => $payment->fresh(),
                    'upload_count' => $payment->proof_upload_count,
                    'max_uploads' => config('notifications.proof_payment.max_uploads', 3)
                ];

            } catch (\Exception $e) {
                Log::error("Failed to upload payment proof", [
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id ?? null,
                    'reference_number' => $booking->reference_number ?? null,
                    'error' => $e->getMessage(),
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType()
                ]);
                
                return [
                    'success' => false,
                    'error_code' => 'upload_failed',
                    'message' => 'Failed to upload proof of payment. Please try again.'
                ];
            }
        });
    }

    /**
     * Reset proof upload count for a payment (admin action)
     */
    public function resetProofUploads(Payment $payment, ?string $reason = null, ?int $adminUserId = null): array
    {
        return DB::transaction(function () use ($payment, $reason, $adminUserId) {
            try {
                $oldGeneration = $payment->proof_upload_generation;
                $newGeneration = $oldGeneration + 1;

                // If currently pending, mark as rejected with reason
                if ($payment->proof_status === 'pending') {
                    $payment->update([
                        'proof_status' => 'rejected',
                        'proof_rejected_reason' => $reason,
                        'proof_rejected_by' => $adminUserId,
                    ]);
                }

                // Reset proof upload counters and increment generation
                $payment->update([
                    'proof_upload_generation' => $newGeneration,
                    'proof_upload_count' => 0,
                    'proof_status' => 'none',
                    'last_proof_notification_at' => null,
                    'proof_last_file_path' => null,
                ]);

                // Log the audit trail
                Log::info("Reset proof uploads for payment {$payment->id} (gen {$oldGeneration}→{$newGeneration})", [
                    'payment_id' => $payment->id,
                    'old_generation' => $oldGeneration,
                    'new_generation' => $newGeneration,
                    'reason' => $reason,
                    'admin_user_id' => $adminUserId,
                ]);

                return [
                    'success' => true,
                    'payment' => $payment->fresh(),
                    'message' => 'Proof upload count reset successfully.'
                ];

            } catch (\Exception $e) {
                Log::error("Failed to reset proof uploads for payment {$payment->id}: " . $e->getMessage());
                
                return [
                    'success' => false,
                    'error_code' => 'reset_failed',
                    'message' => 'Failed to reset proof uploads. Please try again.'
                ];
            }
        });
    }

    /**
     * Update proof status (admin accept/reject)
     */
    public function updateProofStatus(Payment $payment, string $status, ?string $reason = null, ?int $adminUserId = null): array
    {
        if (!in_array($status, ['accepted', 'rejected'])) {
            return [
                'success' => false,
                'error_code' => 'invalid_status',
                'message' => 'Invalid proof status. Must be accepted or rejected.'
            ];
        }

        try {
            $updateData = ['proof_status' => $status];
            
            if ($status === 'rejected') {
                $updateData['proof_rejected_reason'] = $reason;
                $updateData['proof_rejected_by'] = $adminUserId;
                $updateData['proof_rejected_at'] = now();
                // Set payment status to failed when proof is rejected
                $updateData['status'] = 'failed';
            } else {
                // Clear rejection data when accepting
                $updateData['proof_rejected_reason'] = null;
                $updateData['proof_rejected_by'] = null;
                $updateData['proof_rejected_at'] = null;
            }

            $payment->update($updateData);

            // Send email notification to guest
            $guestEmail = $payment->booking->guest_email ?? $payment->booking->user?->email;
            
            if ($guestEmail) {
                if ($status === 'accepted') {
                    // Send "Payment Confirmed" email when proof is accepted
                    EmailTrackingService::sendWithTracking(
                        $guestEmail,
                        new ProofPaymentAcceptedMail($payment),
                        'proof_payment_accepted',
                        [
                            'payment_id' => $payment->id,
                            'booking_id' => $payment->booking_id,
                            'booking_reference' => $payment->booking->reference_number,
                            'payment_amount' => $payment->amount,
                            'admin_user_id' => $adminUserId
                        ]
                    );
                } else if ($status === 'rejected') {
                    // Send proof rejection email first
                    EmailTrackingService::sendWithTracking(
                        $guestEmail,
                        new ProofPaymentRejectedMail($payment, $reason),
                        'proof_payment_rejected',
                        [
                            'payment_id' => $payment->id,
                            'booking_id' => $payment->booking_id,
                            'booking_reference' => $payment->booking->reference_number,
                            'payment_amount' => $payment->amount,
                            'rejection_reason' => $reason,
                            'admin_user_id' => $adminUserId
                        ]
                    );

                    // Automatically cancel the booking when proof is rejected
                    $this->cancelBookingForRejectedProof($payment->booking, $reason, $adminUserId);
                }
            } else {
                Log::warning("No guest email found for payment {$payment->id} - skipping notification", [
                    'payment_id' => $payment->id,
                    'booking_id' => $payment->booking_id,
                    'booking_reference' => $payment->booking->reference_number,
                    'status' => $status
                ]);
            }

            Log::info("Payment proof status updated for payment {$payment->id}", [
                'payment_id' => $payment->id,
                'proof_status' => $status,
                'payment_status' => $status === 'rejected' ? 'failed' : $payment->status,
                'reason' => $reason,
                'admin_user_id' => $adminUserId,
            ]);

            $message = $status === 'rejected' 
                ? "Proof rejected and payment marked as failed."
                : "Proof status updated to {$status}.";

            return [
                'success' => true,
                'payment' => $payment->fresh(),
                'message' => $message
            ];

        } catch (\Exception $e) {
            Log::error("Failed to update proof status for payment {$payment->id}: " . $e->getMessage());
            
            return [
                'success' => false,
                'error_code' => 'status_update_failed',
                'message' => 'Failed to update proof status. Please try again.'
            ];
        }
    }

    /**
     * Store proof file using local storage (same approach as existing PaymentController)
     */
    private function storeProofFile(UploadedFile $file, string $referenceNumber, int $paymentId): ?string
    {
        try {
            $maxDimension = 1920;
            $mime = $file->getMimeType();
            $sourcePath = $file->getRealPath();
            [$width, $height] = @getimagesize($sourcePath) ?: [null, null];
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');

            $targetDir = 'payment-proofs/' . preg_replace('/[^A-Za-z0-9\-_.]/', '-', $referenceNumber);
            $filename = 'payment_' . $paymentId . '_' . now()->format('YmdHis') . '_' . Str::random(8) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            $relativePath = $targetDir . '/' . $filename;

            $shouldResize = $width && $height && ($width > $maxDimension || $height > $maxDimension);

            // If we cannot determine size or mime not supported, store original
            if (!$shouldResize) {
                Storage::disk('public')->putFileAs($targetDir, $file, $filename);
                return $relativePath;
            }

            // Create image resource and resample (same logic as PaymentController)
            $ratio = min($maxDimension / $width, $maxDimension / $height);
            $newWidth = (int) floor($width * $ratio);
            $newHeight = (int) floor($height * $ratio);
            $dst = imagecreatetruecolor($newWidth, $newHeight);

            // Load source
            $src = null;
            if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) {
                $src = @imagecreatefromjpeg($sourcePath);
            } elseif (str_contains($mime, 'png')) {
                $src = @imagecreatefrompng($sourcePath);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            } elseif (str_contains($mime, 'webp') && function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($sourcePath);
            }

            if (!$src) {
                Storage::disk('public')->putFileAs($targetDir, $file, $filename);
                return $relativePath;
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Save to temp and move to storage
            $tmp = tempnam(sys_get_temp_dir(), 'proof_');
            if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) {
                imagejpeg($dst, $tmp, 80);
            } elseif (str_contains($mime, 'png')) {
                imagepng($dst, $tmp, 6);
            } elseif (str_contains($mime, 'webp') && function_exists('imagewebp')) {
                imagewebp($dst, $tmp, 80);
            } else {
                // Fallback to original
                imagedestroy($dst);
                imagedestroy($src);
                Storage::disk('public')->putFileAs($targetDir, $file, $filename);
                return $relativePath;
            }

            imagedestroy($dst);
            imagedestroy($src);

            $stream = fopen($tmp, 'r');
            Storage::disk('public')->put($relativePath, $stream);
            fclose($stream);
            @unlink($tmp);

            return $relativePath;
        } catch (\Throwable $e) {
            Log::warning('Proof image optimization failed: ' . $e->getMessage());
            // Store original if optimization fails
            try {
                $targetDir = 'payment-proofs/' . preg_replace('/[^A-Za-z0-9\-_.]/', '-', $referenceNumber);
                $filename = 'payment_' . $paymentId . '_' . now()->format('YmdHis') . '_' . Str::random(8) . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');
                Storage::disk('public')->putFileAs($targetDir, $file, $filename);
                return $targetDir . '/' . $filename;
            } catch (\Throwable $e2) {
                Log::error('Failed storing proof image: ' . $e2->getMessage());
                return null;
            }
        }
    }

    /**
     * Delete proof file from local storage
     */
    private function deleteProofFile(string $filePath): void
    {
        try {
            // Only delete local storage files, not URLs
            if (!str_contains($filePath, 'http') && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to delete proof file: {$filePath}. Error: " . $e->getMessage());
        }
    }

    /**
     * Automatically cancel booking when proof of payment is rejected
     */
    private function cancelBookingForRejectedProof(Booking $booking, ?string $rejectionReason, ?int $adminUserId): void
    {
        try {
            // Check if booking can be cancelled
            if (!$this->cancellationService->canCancel($booking)) {
                Log::warning("Cannot cancel booking {$booking->id} - booking status: {$booking->status}", [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->reference_number,
                    'booking_status' => $booking->status,
                    'admin_user_id' => $adminUserId
                ]);
                return;
            }

            // Create cancellation reason combining the proof rejection reason
            $cancellationReason = config('booking.cancellation_reasons.proof_rejected_invalid');
            if ($rejectionReason) {
                $cancellationReason .= " - {$rejectionReason}";
            }

            // Cancel the booking
            $result = $this->cancellationService->cancelBooking(
                $booking,
                $cancellationReason,
                $adminUserId ?? 0 // Use 0 for system cancellation if no admin user
            );

            if ($result['success']) {
                Log::info("Booking automatically cancelled due to rejected proof", [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->reference_number,
                    'cancellation_reason' => $cancellationReason,
                    'admin_user_id' => $adminUserId,
                    'proof_rejection_reason' => $rejectionReason
                ]);
            } else {
                Log::error("Failed to automatically cancel booking for rejected proof", [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->reference_number,
                    'error_code' => $result['error_code'] ?? 'unknown',
                    'error_message' => $result['message'] ?? 'Unknown error',
                    'admin_user_id' => $adminUserId
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Exception occurred while cancelling booking for rejected proof", [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'error' => $e->getMessage(),
                'admin_user_id' => $adminUserId
            ]);
        }
    }
}
