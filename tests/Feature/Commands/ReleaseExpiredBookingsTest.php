<?php

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\instance;
use function Pest\Laravel\mock;

describe('ReleaseExpiredBookings command', function () {

    it('cancels and unlocks expired bookings', function () {
        $booking = \App\Models\Booking::factory()->create([
            'status' => 'pending',
            'reserved_until' => now()->subMinutes(20),
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
});
