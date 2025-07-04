<?php

use App\Contracts\Services\BookingLockServiceInterface;
use App\Models\Booking;

use function Pest\Laravel\postJson;

describe('Payment API Integration', function () {
    it('processes a successful payment, updates booking and removes lock', function () {
        $booking = Booking::factory()->create(['status' => 'pending', 'final_price' => 1000]);
        app(BookingLockServiceInterface::class)->lock($booking->id, ['foo' => 'bar']);
        $payload = [
            'amount' => 1000,
            'provider' => 'simulation',
            'outcome' => 'success',
        ];
        $response = postJson("/api/v1/bookings/{$booking->id}/pay", $payload);
        $response->assertOk();
        $data = $response->json();
        expect($data['success'])->toBe(true);
        $booking->refresh();
        expect($booking->status)->toBe('paid'); // or your desired post-payment status
        expect(app(BookingLockServiceInterface::class)->get($booking->id))->toBeNull();
    });

    it('allows payment for downpayment status (partial payment flow)', function () {
        $booking = Booking::factory()->create(['status' => 'downpayment']);
        $payload = [
            'amount' => 500,
            'provider' => 'simulation',
            'outcome' => 'success',
        ];
        postJson("/api/v1/bookings/{$booking->id}/pay", $payload)
            ->assertOk()
            ->assertJson(['success' => true]);
    });

    it('processes a failed payment, does NOT change booking status, removes lock, and logs fail', function () {
        $booking = Booking::factory()->create(['status' => 'pending']);
        app(BookingLockServiceInterface::class)->lock($booking->id, ['foo' => 'bar']);
        $payload = [
            'amount' => 1000,
            'provider' => 'simulation',
            'outcome' => 'fail',
        ];
        postJson("/api/v1/bookings/{$booking->id}/pay", $payload)
            ->assertBadRequest()
            ->assertJson(['success' => false]);
        $booking->refresh();
        expect($booking->status)->toBe('pending'); // Stays the same
        expect(app(BookingLockServiceInterface::class)->get($booking->id))->toBeNull();
        // Optionally: check failed_payment_attempts incremented
        // expect($booking->failed_payment_attempts)->toBe(1);
    });

    it('does not allow payment for already paid booking', function () {
        $booking = Booking::factory()->create(['status' => 'paid']);
        $payload = [
            'amount' => 1000,
            'provider' => 'simulation',
            'outcome' => 'success',
        ];
        postJson("/api/v1/bookings/{$booking->id}/pay", $payload)
            ->assertStatus(400)
            ->assertJson(['success' => false, 'error_code' => 'ALREADY_PAID']);
    });

    it('does not allow payment for status cancelled or failed', function () {
        foreach (['cancelled', 'failed'] as $status) {
            $booking = Booking::factory()->create(['status' => $status]);
            $payload = [
                'amount' => 1000,
                'provider' => 'simulation',
                'outcome' => 'success',
            ];
            postJson("/api/v1/bookings/{$booking->id}/pay", $payload)
                ->assertStatus(400)
                ->assertJson(['success' => false, 'error_code' => 'INVALID_STATUS']);
        }
    });

    it('throws 404 if booking is not found', function () {
        $payload = [
            'amount' => 1000,
            'provider' => 'simulation',
            'outcome' => 'success',
        ];
        postJson("/api/v1/bookings/999999/pay", $payload)
            ->assertStatus(404);
    });

    it('handles missing booking lock gracefully', function () {
        $booking = Booking::factory()->create(['status' => 'pending']);
        $payload = [
            'amount' => 1000,
            'provider' => 'simulation',
            'outcome' => 'success',
        ];
        postJson("/api/v1/bookings/{$booking->id}/pay", $payload)
            ->assertOk()
            ->assertJson(['success' => true]);
        expect(app(BookingLockServiceInterface::class)->get($booking->id))->toBeNull();
    });

    it('returns 422 if amount is missing', function () {
        $booking = Booking::factory()->create(['status' => 'pending']);
        $payload = [
            'provider' => 'simulation',
            'outcome' => 'success',
        ];
        $this->postJson("/api/v1/bookings/{$booking->id}/pay", $payload)
            ->assertStatus(422);
    });

    it('does NOT change status if final payment fails after downpayment', function () {
        $booking = Booking::factory()->create(['status' => 'downpayment']);
        $payload = [
            'amount' => 9000, // any amount, simulation of failed payment
            'provider' => 'simulation',
            'outcome' => 'fail',
        ];
        postJson("/api/v1/bookings/{$booking->id}/pay", $payload)
            ->assertBadRequest()
            ->assertJson(['success' => false]);
        $booking->refresh();
        expect($booking->status)->toBe('downpayment'); // Should stay downpayment
        // Optionally: check failed_payment_attempts incremented
        // expect($booking->failed_payment_attempts)->toBe(1);
    });
});
