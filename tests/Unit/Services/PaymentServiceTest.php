<?php

use App\Contracts\Repositories\BookingRepositoryInterface;
use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Contracts\Services\BookingLockServiceInterface;
use App\Contracts\Services\BookingServiceInterface;
use App\Contracts\Services\PaymentGatewayInterface;
use App\DTO\Payments\PaymentGatewayResultDTO;
use App\DTO\Payments\PaymentRequestDTO;
use App\Models\Booking;
use App\Services\Payments\PaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

describe('PaymentService', function () {
    beforeEach(function () {
        $this->gateway = mock(PaymentGatewayInterface::class);
        $this->bookingService = mock(BookingServiceInterface::class);
        $this->repo = mock(PaymentRepositoryInterface::class);
        $this->bookingLockService = mock(BookingLockServiceInterface::class);
        $this->bookingRepo = mock(BookingRepositoryInterface::class);
        $this->service = new PaymentService($this->gateway, $this->bookingService, $this->repo, $this->bookingLockService, $this->bookingRepo);
    });

    it('processes a successful payment, marks booking as paid/downpayment, creates payment record, and cleans up lock', function () {
        $booking = Booking::factory()->create(['final_price' => 10000]);
        $this->gateway->shouldReceive('execute')->once()->andReturn(new PaymentGatewayResultDTO(true));
        $this->bookingService->shouldReceive('markPaid')->once();
        $this->repo->shouldReceive('create')->once();
        $this->bookingLockService->shouldReceive('delete')->once();
        $dto = new PaymentRequestDTO($booking->reference_number, 10000, 'simulation', 'success');
        $result = $this->service->execute($dto);
        expect($result->success)->toBeTrue();
    });

    it('processes a failed payment, does not update booking status, creates payment record, and cleans up lock', function () {
        $booking = Booking::factory()->create(['final_price' => 10000]);
        $this->gateway->shouldReceive('execute')->once()->andReturn(new PaymentGatewayResultDTO(false, 'ERR', 'fail'));
        $this->bookingService->shouldNotHaveReceived('markPaid');
        $this->bookingService->shouldReceive('markPaymentFailed')->once();
        $this->repo->shouldReceive('create')->once();
        $this->bookingLockService->shouldReceive('delete')->once();
        $dto = new PaymentRequestDTO($booking->reference_number, 10000, 'simulation', 'fail');
        $result = $this->service->execute($dto);
        expect($result->success)->toBeFalse();
    });

    it('always calls cleanupLock even if an exception occurs', function () {
        $booking = Booking::factory()->create(['final_price' => 10000]);
        $this->gateway->shouldReceive('execute')->once()->andThrow(new Exception('Gateway error'));
        $this->bookingLockService->shouldReceive('delete')->once();
        $dto = new PaymentRequestDTO($booking->reference_number, 10000, 'simulation', 'success');
        $result = $this->service->execute($dto);
        expect($result->success)->toBeFalse();
        expect($result->errorCode)->toBe('EXCEPTION');
    });

    it('does not allow payment if already paid', function () {
        $booking = Booking::factory()->create(['status' => 'paid', 'final_price' => 10000]);
        $dto = new PaymentRequestDTO($booking->reference_number, 10000, 'simulation', 'success');
        $result = $this->service->execute($dto);
        expect($result->success)->toBeFalse();
        expect($result->errorCode)->toBe('ALREADY_PAID');
    });

    it('blocks payment if status is cancelled or failed', function () {
        foreach (['cancelled', 'failed'] as $status) {
            $booking = Booking::factory()->create(['status' => $status, 'final_price' => 10000]);
            $dto = new PaymentRequestDTO($booking->reference_number, 10000, 'simulation', 'success');
            $result = $this->service->execute($dto);
            expect($result->success)->toBeFalse();
            expect($result->errorCode)->toBe('INVALID_STATUS');
        }
    });

    it('allows payment if status is pending or downpayment', function () {
        foreach (['pending', 'downpayment'] as $status) {
            $booking = Booking::factory()->create(['status' => $status, 'final_price' => 10000]);
            $this->gateway->shouldReceive('execute')->once()->andReturn(new PaymentGatewayResultDTO(true));
            $this->bookingService->shouldReceive('markPaid')->once();
            $this->repo->shouldReceive('create')->once();
            $this->bookingLockService->shouldReceive('delete')->once();
            $dto = new PaymentRequestDTO($booking->reference_number, 10000, 'simulation', 'success');
            $result = $this->service->execute($dto);
            expect($result->success)->toBeTrue();
        }
    });

    it('throws ModelNotFoundException if booking is not found', function () {
        $dto = new PaymentRequestDTO(999999, 1091, 'simulation', 'success');
        expect(fn() => $this->service->execute($dto))->toThrow(ModelNotFoundException::class);
    });

    it('handles missing booking lock gracefully', function () {
        $booking = Booking::factory()->create(['status' => 'pending', 'final_price' => 10000]);
        $this->gateway->shouldReceive('execute')->once()->andReturn(new PaymentGatewayResultDTO(true));
        $this->bookingService->shouldReceive('markPaid')->once();
        $this->repo->shouldReceive('create')->once();
        $this->bookingLockService->shouldReceive('delete')->once();
        $dto = new PaymentRequestDTO($booking->reference_number, 10000, 'simulation', 'success');
        $result = $this->service->execute($dto);
        expect($result->success)->toBeTrue();
    });
});
