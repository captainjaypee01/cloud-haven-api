<?php

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\instance;
use function Pest\Laravel\mock;

describe('ReleaseExpiredBookings command', function () {

    it('cancels and unlocks expired bookings with no payments at all', function () {
        $booking = \App\Models\Booking::factory()->create([
            'status' => 'pending',
            'reserved_until' => now()->subMinutes(20),
        ]);
        // No payments created
        
        $mockLockService = mock(\App\Services\Bookings\BookingLockService::class);
        $mockLockService->shouldReceive('delete')->once()->with($booking->id);

        instance(\App\Services\Bookings\BookingLockService::class, $mockLockService);
        artisan('bookings:release-expired')->assertExitCode(0);
        assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    });

    it('does not cancel expired bookings with accepted proof of payment', function () {
        $booking = \App\Models\Booking::factory()->create([
            'status' => 'pending',
            'reserved_until' => now()->subMinutes(20),
        ]);
        
        // Create a payment with accepted proof
        \App\Models\Payment::factory()->create([
            'booking_id' => $booking->id,
            'proof_status' => 'accepted', // Accepted proof
        ]);
        
        $mockLockService = mock(\App\Services\Bookings\BookingLockService::class);
        $mockLockService->shouldNotReceive('delete');

        instance(\App\Services\Bookings\BookingLockService::class, $mockLockService);
        artisan('bookings:release-expired')->assertExitCode(0);
        assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending', // Should remain pending
        ]);
    });

    it('does not cancel expired bookings with pending proof of payment', function () {
        $booking = \App\Models\Booking::factory()->create([
            'status' => 'pending',
            'reserved_until' => now()->subMinutes(20),
        ]);
        
        // Create a payment with pending proof
        \App\Models\Payment::factory()->create([
            'booking_id' => $booking->id,
            'proof_status' => 'pending', // Pending proof - should not cancel
        ]);
        
        $mockLockService = mock(\App\Services\Bookings\BookingLockService::class);
        $mockLockService->shouldNotReceive('delete');

        instance(\App\Services\Bookings\BookingLockService::class, $mockLockService);
        artisan('bookings:release-expired')->assertExitCode(0);
        assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending', // Should remain pending
        ]);
    });

    it('cancels expired bookings with all rejected proofs after grace period', function () {
        $booking = \App\Models\Booking::factory()->create([
            'status' => 'pending',
            'reserved_until' => now()->subMinutes(20),
        ]);
        
        // Create a payment with rejected proof that's past grace period (3 days ago)
        \App\Models\Payment::factory()->create([
            'booking_id' => $booking->id,
            'proof_status' => 'rejected',
            'proof_rejected_at' => now()->subDays(3), // Past 2-day grace period
        ]);
        
        $mockLockService = mock(\App\Services\Bookings\BookingLockService::class);
        $mockLockService->shouldReceive('delete')->once()->with($booking->id);

        instance(\App\Services\Bookings\BookingLockService::class, $mockLockService);
        artisan('bookings:release-expired')->assertExitCode(0);
        assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    });

    it('does not cancel expired bookings with rejected proofs within grace period', function () {
        $booking = \App\Models\Booking::factory()->create([
            'status' => 'pending',
            'reserved_until' => now()->subMinutes(20),
        ]);
        
        // Create a payment with rejected proof that's within grace period (1 day ago)
        \App\Models\Payment::factory()->create([
            'booking_id' => $booking->id,
            'proof_status' => 'rejected',
            'proof_rejected_at' => now()->subDay(), // Within 2-day grace period
        ]);
        
        $mockLockService = mock(\App\Services\Bookings\BookingLockService::class);
        $mockLockService->shouldNotReceive('delete');

        instance(\App\Services\Bookings\BookingLockService::class, $mockLockService);
        artisan('bookings:release-expired')->assertExitCode(0);
        assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'pending', // Should remain pending
        ]);
    });
});
