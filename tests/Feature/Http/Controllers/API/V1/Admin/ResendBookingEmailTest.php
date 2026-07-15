<?php

use App\Mail\BookingConfirmation;
use App\Mail\BookingReservation;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->staff = User::factory()->create([
        'role' => 'staff',
    ]);
});

it('queues a booking reservation email when admin resends reservation', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'guest_email' => 'guest@example.com',
        'guest_name' => 'Test Guest',
    ]);

    $response = $this->actingAs($this->staff)
        ->withHeader('X-TEST-USER-ID', (string) $this->staff->id)
        ->postJson("/api/v1/admin/bookings/{$booking->id}/resend-email", [
            'email_type' => 'reservation',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.email_type', 'reservation')
        ->assertJsonPath('data.recipient', 'guest@example.com');

    Mail::assertQueued(BookingReservation::class, function ($mail) use ($booking) {
        return $mail->booking->id === $booking->id;
    });
});

it('queues a booking confirmation email when admin resends confirmation', function () {
    $booking = Booking::factory()->create([
        'status' => 'downpayment',
        'guest_email' => 'guest@example.com',
        'guest_name' => 'Test Guest',
    ]);

    $response = $this->actingAs($this->staff)
        ->withHeader('X-TEST-USER-ID', (string) $this->staff->id)
        ->postJson("/api/v1/admin/bookings/{$booking->id}/resend-email", [
            'email_type' => 'confirmation',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.email_type', 'confirmation');

    Mail::assertQueued(BookingConfirmation::class, function ($mail) use ($booking) {
        return $mail->booking->id === $booking->id;
    });
});

it('rejects resend email for cancelled bookings', function () {
    $booking = Booking::factory()->create([
        'status' => 'cancelled',
        'guest_email' => 'guest@example.com',
    ]);

    $response = $this->actingAs($this->staff)
        ->withHeader('X-TEST-USER-ID', (string) $this->staff->id)
        ->postJson("/api/v1/admin/bookings/{$booking->id}/resend-email", [
            'email_type' => 'reservation',
        ]);

    $response->assertStatus(422);
    Mail::assertNothingQueued();
});
