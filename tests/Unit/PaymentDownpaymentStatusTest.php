<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Http\Resources\Booking\BookingResource;
use App\Http\Controllers\API\V1\Admin\PaymentController;
use App\Contracts\Services\PaymentServiceInterface;
use App\Contracts\Services\BookingServiceInterface;
use App\Services\PaymentProofService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->paymentService = $this->createMock(PaymentServiceInterface::class);
    $this->bookingService = $this->createMock(BookingServiceInterface::class);
    $this->paymentProofService = $this->createMock(PaymentProofService::class);
    
    $this->controller = new PaymentController(
        $this->paymentService,
        $this->bookingService,
        $this->paymentProofService
    );
});

test('manual downpayment status is shown when set', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'reference_number' => 'TEST-001'
    ]);

    // Create a payment with manual downpayment status
    $payment = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 1000,
        'downpayment_status' => 'downpayment'
    ]);

    // Use reflection to access the private method
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('calculateDownpaymentStatus');
    $method->setAccessible(true);

    // Test the payment (should show downpayment status because it's manually set)
    $result = $method->invoke($this->controller, $payment, $booking);
    expect($result)->toBe('downpayment');
});

test('downpayment status is shown for first pending payment (backward compatibility)', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'reference_number' => 'TEST-001'
    ]);

    // Create payments: first pending, second paid
    $payment1 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'pending',
        'amount' => 1000,
        'created_at' => now()->subHours(2)
    ]);

    $payment2 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 2000,
        'created_at' => now()->subHour()
    ]);

    // Use reflection to access the private method
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('calculateDownpaymentStatus');
    $method->setAccessible(true);

    // Test the first payment (should show downpayment status)
    $result1 = $method->invoke($this->controller, $payment1, $booking);
    expect($result1)->toBe('downpayment');

    // Test the second payment (should not show downpayment status)
    $result2 = $method->invoke($this->controller, $payment2, $booking);
    expect($result2)->toBeNull();
});

test('downpayment status is not shown for non-pending payments', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'reference_number' => 'TEST-002'
    ]);

    // Create a paid payment
    $payment = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 1000
    ]);

    // Use reflection to access the private method
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('calculateDownpaymentStatus');
    $method->setAccessible(true);

    // Test the paid payment (should not show downpayment status)
    $result = $method->invoke($this->controller, $payment, $booking);
    expect($result)->toBeNull();
});

test('downpayment status is shown for first pending payment after failed payment', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'reference_number' => 'TEST-003'
    ]);

    // Create payments: first failed, second pending
    $payment1 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'failed',
        'amount' => 1000,
        'created_at' => now()->subHours(2)
    ]);

    $payment2 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'pending',
        'amount' => 2000,
        'created_at' => now()->subHour()
    ]);

    // Use reflection to access the private method
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('calculateDownpaymentStatus');
    $method->setAccessible(true);

    // Test the first payment (failed, should not show downpayment status)
    $result1 = $method->invoke($this->controller, $payment1, $booking);
    expect($result1)->toBeNull();

    // Test the second payment (first pending, should show downpayment status)
    $result2 = $method->invoke($this->controller, $payment2, $booking);
    expect($result2)->toBe('downpayment');
});

test('downpayment status is not shown for pending payment after paid payment', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'status' => 'downpayment',
        'reference_number' => 'TEST-004'
    ]);

    // Create payments: first paid, second pending
    $payment1 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 1000,
        'created_at' => now()->subHours(2)
    ]);

    $payment2 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'pending',
        'amount' => 2000,
        'created_at' => now()->subHour()
    ]);

    // Use reflection to access the private method
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('calculateDownpaymentStatus');
    $method->setAccessible(true);

    // Test the first payment (paid, should not show downpayment status)
    $result1 = $method->invoke($this->controller, $payment1, $booking);
    expect($result1)->toBeNull();

    // Test the second payment (pending but after paid payment, should not show downpayment status)
    $result2 = $method->invoke($this->controller, $payment2, $booking);
    expect($result2)->toBeNull();
});

test('booking resource includes downpayment status in payments', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'reference_number' => 'TEST-005'
    ]);

    // Create payments: first pending, second paid
    $payment1 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'pending',
        'amount' => 1000,
        'created_at' => now()->subHours(2)
    ]);

    $payment2 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 2000,
        'created_at' => now()->subHour()
    ]);

    // Load the booking with payments
    $booking->load('payments');

    // Create the resource
    $resource = new BookingResource($booking);
    $data = $resource->toArray(request());

    // Check that the first payment has downpayment status
    expect($data['payments'][0]['downpayment_status'])->toBe('downpayment');
    
    // Check that the second payment does not have downpayment status
    expect($data['payments'][1]['downpayment_status'])->toBeNull();
});

test('booking status becomes downpayment when payment has manual downpayment status', function () {
    // Create a booking with high total price
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'reference_number' => 'TEST-006',
        'final_price' => 10000, // High price
        'discount_amount' => 0
    ]);

    // Create a payment with manual downpayment status but small amount (less than 50%)
    $payment = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 2000, // Only 20% of total
        'downpayment_status' => 'downpayment'
    ]);

    // Use the booking service to recalculate status
    $bookingService = app(\App\Contracts\Services\BookingServiceInterface::class);
    $bookingService->markPaid($booking);

    // Refresh the booking
    $booking->refresh();

    // The booking should be marked as downpayment even though the amount is less than 50%
    expect($booking->status)->toBe('downpayment');
});

test('only one payment can be marked as downpayment per booking', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'reference_number' => 'TEST-007'
    ]);

    // Create first payment with downpayment status
    $payment1 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 1000,
        'downpayment_status' => 'downpayment'
    ]);

    // Create second payment without downpayment status
    $payment2 = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 2000,
        'downpayment_status' => null
    ]);

    // Use reflection to access the private method
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('calculateDownpaymentStatus');
    $method->setAccessible(true);

    // First payment should show downpayment status
    $result1 = $method->invoke($this->controller, $payment1, $booking);
    expect($result1)->toBe('downpayment');

    // Second payment should not show downpayment status
    $result2 = $method->invoke($this->controller, $payment2, $booking);
    expect($result2)->toBeNull();
});

test('existing downpayment can be edited to change status', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'status' => 'downpayment',
        'reference_number' => 'TEST-008'
    ]);

    // Create a payment with downpayment status
    $payment = Payment::factory()->create([
        'booking_id' => $booking->id,
        'status' => 'paid',
        'amount' => 1000,
        'downpayment_status' => 'downpayment'
    ]);

    // Simulate editing the payment to remove downpayment status
    $payment->update(['downpayment_status' => 'none']);

    // Use reflection to access the private method
    $reflection = new ReflectionClass($this->controller);
    $method = $reflection->getMethod('calculateDownpaymentStatus');
    $method->setAccessible(true);

    // Payment should no longer show downpayment status
    $result = $method->invoke($this->controller, $payment, $booking);
    expect($result)->toBeNull();
});
