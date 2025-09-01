<?php

use App\Models\Booking;
use App\Models\User;
use App\Services\Bookings\BookingCancellationService;
use function Pest\Laravel\mock;

describe('BookingCancellationService', function () {

    beforeEach(function () {
        $mockLockService = mock(\App\Contracts\Services\BookingLockServiceInterface::class);
        $mockLockService->shouldReceive('delete')->andReturn(true);
        $this->service = new BookingCancellationService($mockLockService);
    });

    it('can cancel a pending booking', function () {
        $booking = Booking::factory()->create(['status' => 'pending']);
        $adminUser = User::factory()->create();

        $result = $this->service->cancelBooking($booking, 'Guest request', $adminUser->id);

        expect($result['success'])->toBeTrue()
            ->and($result['message'])->toBe('Booking cancelled successfully.')
            ->and($booking->fresh()->status)->toBe('cancelled')
            ->and($booking->fresh()->cancellation_reason)->toBe('Guest request')
            ->and($booking->fresh()->cancelled_by)->toBe($adminUser->id);
    });

    it('can cancel a failed booking', function () {
        $booking = Booking::factory()->create(['status' => 'failed']);
        $adminUser = User::factory()->create();

        $result = $this->service->cancelBooking($booking, 'System error', $adminUser->id);

        expect($result['success'])->toBeTrue()
            ->and($booking->fresh()->status)->toBe('cancelled');
    });

    it('cannot cancel an already cancelled booking', function () {
        $booking = Booking::factory()->create(['status' => 'cancelled']);
        $adminUser = User::factory()->create();

        $result = $this->service->cancelBooking($booking, 'Test reason', $adminUser->id);

        expect($result['success'])->toBeFalse()
            ->and($result['error_code'])->toBe('cannot_cancel')
            ->and($result['message'])->toBe('This booking cannot be cancelled.');
    });

    it('cannot cancel a paid booking', function () {
        $booking = Booking::factory()->create(['status' => 'paid']);
        $adminUser = User::factory()->create();

        $result = $this->service->cancelBooking($booking, 'Test reason', $adminUser->id);

        expect($result['success'])->toBeFalse()
            ->and($result['error_code'])->toBe('cannot_cancel');
    });

    it('cannot cancel a downpayment booking', function () {
        $booking = Booking::factory()->create(['status' => 'downpayment']);
        $adminUser = User::factory()->create();

        $result = $this->service->cancelBooking($booking, 'Test reason', $adminUser->id);

        expect($result['success'])->toBeFalse()
            ->and($result['error_code'])->toBe('cannot_cancel');
    });

    it('returns available cancellation reasons', function () {
        $reasons = $this->service->getCancellationReasons();

        // Should only return manual admin cancellation reasons (excludes system automatic ones)
        expect($reasons)->toHaveKeys([
            'guest_request',
            'invalid_booking',
            'system_error',
            'operational_issue',
            'other'
        ]);
        
        // Should NOT include system automatic reasons
        expect($reasons)->not->toHaveKey('no_payment_received');
        expect($reasons)->not->toHaveKey('rejected_proof_expired');
    });

    it('returns specific cancellation reason by key', function () {
        $reason = $this->service->getCancellationReason('guest_request');
        expect($reason)->toBe('Cancelled at guest request');
        
        // Returns the key itself if not found
        $unknownReason = $this->service->getCancellationReason('unknown_key');
        expect($unknownReason)->toBe('unknown_key');
    });

    it('returns system automatic cancellation reasons', function () {
        $systemReasons = $this->service->getSystemCancellationReasons();
        
        expect($systemReasons)->toHaveKeys([
            'no_payment_received',
            'rejected_proof_expired'
        ]);
        
        expect($systemReasons['no_payment_received'])->toBe('No proof of payment received within the required timeframe');
        expect($systemReasons['rejected_proof_expired'])->toBe('Proof of payment rejected and grace period expired');
    });

    it('correctly identifies cancellable bookings', function () {
        $pendingBooking = Booking::factory()->create(['status' => 'pending']);
        $failedBooking = Booking::factory()->create(['status' => 'failed']);
        $paidBooking = Booking::factory()->create(['status' => 'paid']);
        $cancelledBooking = Booking::factory()->create(['status' => 'cancelled']);

        expect($this->service->canCancel($pendingBooking))->toBeTrue()
            ->and($this->service->canCancel($failedBooking))->toBeTrue()
            ->and($this->service->canCancel($paidBooking))->toBeFalse()
            ->and($this->service->canCancel($cancelledBooking))->toBeFalse();
    });
});
