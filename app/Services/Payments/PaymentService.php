<?php

namespace App\Services\Payments;

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Contracts\Services\BookingLockServiceInterface;
use App\Contracts\Services\BookingServiceInterface;
use App\Contracts\Services\PaymentGatewayInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\DTO\Payments\PaymentRequestDTO;
use App\DTO\Payments\PaymentResultDTO;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private BookingServiceInterface $bookingService,
        private PaymentRepositoryInterface $paymentRepo,
        private BookingLockServiceInterface $bookingLockService,
        private BookingRepositoryInterface $bookingRepo,
    ) {}

    public function execute(PaymentRequestDTO $dto): PaymentResultDTO
    {
        $booking = $this->bookingRepo->getByReferenceNumber($dto->referenceNumber);

        if (in_array($booking->status, ['paid', 'cancelled', 'failed'])) {
            return new PaymentResultDTO(false, 'INVALID_STATUS', 'Booking cannot accept payments.');
        }

        DB::beginTransaction();
        try {
            if (!$dto->isManual) {
                $result = $this->gateway->execute($dto);
                $status = $result->success ? 'paid' : 'failed';
                $errorCode = $result->errorCode;
                $errorMessage = $result->errorMessage;
            } else {
                $status = $dto->status ?? 'paid'; // Admin manually sets status
                $result = (object)['success' => $status === 'paid', 'errorCode' => null, 'errorMessage' => null];
                $errorCode = null;
                $errorMessage = null;
            }

            $payment = $this->paymentRepo->create([
                'booking_id' => $booking->id,
                'provider' => $dto->provider,
                'status' => $status,
                'amount' => $dto->amount,
                'transaction_id' => $dto->transactionId,
                'remarks' => $dto->remarks,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

            if ($status === 'paid') {
                $this->bookingService->markPaid($booking);
            } elseif ($status === 'failed') {
                $this->bookingService->markPaymentFailed($booking);
            }

            DB::commit();

            // Always notify via email for successful payments
            // if ($status === 'paid') {
            //     $downpayment = $booking->payments()->where('status', 'paid')->sum('amount');
            //     Mail::to($booking->guest_email)->queue(new \App\Mail\BookingConfirmation($booking, $downpayment));
            // }
            // Determine the first successful payment
            $isFirstSuccessfulPayment = $booking->payments()->where('status', 'paid')->count() === 1;
            if ($dto->isNotifyGuest) {
                if ($status === 'paid') {
                    if ($isFirstSuccessfulPayment) {
                        Mail::to($booking->guest_email)->queue(new \App\Mail\BookingConfirmation($booking, $payment->amount));
                        Mail::to($booking->guest_email)->queue(new \App\Mail\PaymentSuccess($booking, $payment));
                    } else {
                        Mail::to($booking->guest_email)->queue(new \App\Mail\PaymentSuccess($booking, $payment));
                    }
                } elseif ($status === 'failed') {
                    // Optional PaymentFailed email
                    Mail::to($booking->guest_email)->queue(new \App\Mail\PaymentFailed($booking, $payment));
                }
            }

            return new PaymentResultDTO(
                $result->success,
                $result->errorCode,
                $result->errorMessage,
                $payment,
                $booking->refresh()
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return new PaymentResultDTO(false, 'EXCEPTION', $e->getMessage());
        } finally {
            $this->bookingLockService->delete($booking->id);
        }
    }
}
