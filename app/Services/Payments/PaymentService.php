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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailTrackingService;

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

        $finalPrice = $booking->final_price;
        if (in_array($booking->status, ['paid', 'cancelled', 'failed'])) {
            if ($booking->status === 'paid') {
                $otherCharges = $booking->otherCharges()->sum('amount');
                $paidAmount = $booking->payments()->where('status', 'paid')->sum('amount');
                if (!(($otherCharges + $finalPrice) > $paidAmount))
                    return new PaymentResultDTO(false, 'INVALID_STATUS', 'Booking cannot accept payments.');
            } else {
                return new PaymentResultDTO(false, 'INVALID_STATUS', 'Booking cannot accept payments.');
            }
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
                        EmailTrackingService::sendWithTracking(
                            $booking->guest_email,
                            new \App\Mail\BookingConfirmation($booking, $payment->amount),
                            'booking_confirmation',
                            [
                                'booking_id' => $booking->id,
                                'reference_number' => $booking->reference_number,
                                'payment_amount' => $payment->amount,
                                'is_first_payment' => true
                            ]
                        );
                        
                        EmailTrackingService::sendWithTracking(
                            $booking->guest_email,
                            new \App\Mail\PaymentSuccess($booking, $payment),
                            'payment_success',
                            [
                                'booking_id' => $booking->id,
                                'reference_number' => $booking->reference_number,
                                'payment_id' => $payment->id,
                                'payment_amount' => $payment->amount
                            ]
                        );
                    } else {
                        EmailTrackingService::sendWithTracking(
                            $booking->guest_email,
                            new \App\Mail\PaymentSuccess($booking, $payment),
                            'payment_success',
                            [
                                'booking_id' => $booking->id,
                                'reference_number' => $booking->reference_number,
                                'payment_id' => $payment->id,
                                'payment_amount' => $payment->amount,
                                'is_additional_payment' => true
                            ]
                        );
                    }
                } elseif ($status === 'failed') {
                    // Optional PaymentFailed email
                    EmailTrackingService::sendWithTracking(
                        $booking->guest_email,
                        new \App\Mail\PaymentFailed($booking, $payment),
                        'payment_failed',
                        [
                            'booking_id' => $booking->id,
                            'reference_number' => $booking->reference_number,
                            'payment_id' => $payment->id,
                            'payment_amount' => $payment->amount
                        ]
                    );
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

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->paymentRepo->list($filters);
    }
}
