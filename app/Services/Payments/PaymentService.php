<?php

namespace App\Services\Payments;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Contracts\Services\BookingLockServiceInterface;
use App\Contracts\Services\BookingServiceInterface;
use App\Contracts\Services\PaymentGatewayInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\DTO\Payments\PaymentRequestDTO;
use App\DTO\Payments\PaymentResultDTO;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private BookingServiceInterface $bookingService,
        private PaymentRepositoryInterface $paymentRepo,
        private BookingLockServiceInterface $bookingLockService
    ) {}

    public function execute(PaymentRequestDTO $dto): PaymentResultDTO
    {
        $booking = Booking::findOrFail($dto->bookingId);

        // Allow payment if 'pending' or 'downpaymnet'
        // Block only if 'paid', 'cancelled', or 'failed'
        if ($booking->status === 'paid') {
            return new PaymentResultDTO(false, 'ALREADY_PAID', 'Booking already paid.');
        }
        if (in_array($booking->status, ['cancelled', 'failed'])) {
            return new PaymentResultDTO(false, 'INVALID_STATUS', 'Booking cannot be paid.');
        }

        DB::beginTransaction();
        try {
            $result = $this->gateway->execute($dto);

            $payment = $this->paymentRepo->create([
                'booking_id'   => $booking->id,
                'provider'     => $dto->provider,
                'status'       => $result->success ? 'paid' : 'failed',
                'amount'       => $dto->amount,
                'error_code'   => $result->errorCode,
                'error_message' => $result->errorMessage,
            ]);

            if ($result->success) {
                $this->bookingService->markPaid($booking);
            } else {
                $this->bookingService->markPaymentFailed($booking);
            }
            // Do NOT change booking status on failed payment unless you want to track attempts

            DB::commit();

            return new PaymentResultDTO(
                $result->success,
                $result->errorCode,
                $result->errorMessage,
                $payment,
                $booking
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::info($e->getMessage());
            return new PaymentResultDTO(false, 'EXCEPTION', $e->getMessage());
        } finally {
            $this->bookingLockService->delete($booking->id);
        }
    }
}
