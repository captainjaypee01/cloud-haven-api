<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Services\Bookings\BookingBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BookingBalanceService::class);
});

test('calculates downpayment as fifty percent of net stay total', function () {
    expect($this->service->calculateDownpaymentAmount(10000))->toBe(5000.0);
});

test('requires downpayment check when status is downpayment or paid', function () {
    $downpaymentBooking = Booking::factory()->create(['status' => 'downpayment']);
    $paidBooking = Booking::factory()->create(['status' => 'paid']);
    $pendingBooking = Booking::factory()->create(['status' => 'pending']);

    expect($this->service->requiresDownpaymentCheck($downpaymentBooking))->toBeTrue();
    expect($this->service->requiresDownpaymentCheck($paidBooking))->toBeTrue();
    expect($this->service->requiresDownpaymentCheck($pendingBooking))->toBeFalse();
});

test('requires downpayment check when guest has paid any amount', function () {
    $booking = Booking::factory()->create(['status' => 'pending']);
    Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 500,
    ]);

    expect($this->service->requiresDownpaymentCheck($booking->fresh()))->toBeTrue();
});

test('compare current and proposed includes balance deltas', function () {
    $booking = Booking::factory()->create([
        'status' => 'downpayment',
        'final_price' => 10000,
        'discount_amount' => 0,
        'total_price' => 8000,
        'meal_price' => 2000,
    ]);

    Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 5000,
    ]);

    $comparison = $this->service->compareCurrentAndProposed($booking->fresh(), [
        'final_price' => 15000,
        'total_room' => 12000,
        'meal_total' => 3000,
        'extra_guest_fee' => 0,
    ]);

    expect($comparison)->toHaveKeys(['current', 'proposed', 'proposed_downpayment_amount']);
    expect($comparison['proposed']['delta_final'])->toBe(5000.0);
    expect($comparison['proposed_downpayment_amount'])->toBe(7500.0);
});
