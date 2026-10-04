<?php

use App\Contracts\Services\BookingLockServiceInterface;
use App\Mail\BookingReservation;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    $this->lockService = Mockery::mock(BookingLockServiceInterface::class);
    $this->lockService->shouldReceive('lock')->byDefault();
    $this->app->instance(BookingLockServiceInterface::class, $this->lockService);

    $this->admin = User::factory()->create(['role' => 'admin']);

    $this->room = Room::factory()->create(['slug' => 'garden-view', 'quantity' => 2]);
    $this->unit1 = RoomUnit::factory()->create([
        'room_id' => $this->room->id,
        'unit_number' => '101',
        'status' => \App\Enums\RoomUnitStatusEnum::AVAILABLE,
    ]);
    $this->unit2 = RoomUnit::factory()->create([
        'room_id' => $this->room->id,
        'unit_number' => '102',
        'status' => \App\Enums\RoomUnitStatusEnum::AVAILABLE,
    ]);

    $this->checkIn = now('Asia/Singapore')->addDays(3)->toDateString();
    $this->checkOut = now('Asia/Singapore')->addDays(4)->toDateString();

    $this->makeBooking = function (array $attributes = [], ?int $unitId = null): Booking {
        $booking = Booking::factory()->create(array_merge([
            'status' => 'cancelled',
            'booking_type' => 'overnight',
            'check_in_date' => $this->checkIn,
            'check_out_date' => $this->checkOut,
            'reserved_until' => now()->subHours(3),
            'cancelled_at' => now()->subHour(),
            'cancellation_reason' => config('booking.cancellation_reasons.no_payment_received'),
        ], $attributes));

        BookingRoom::factory()->create([
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'room_unit_id' => $unitId ?? $this->unit1->id,
            'adults' => 2,
            'children' => 0,
        ]);

        return $booking;
    };

    $this->reactivate = fn (Booking $booking, array $payload = [], ?User $user = null) => $this
        ->actingAs($user ?? $this->admin)
        ->withHeader('X-TEST-USER-ID', (string) ($user ?? $this->admin)->id)
        ->postJson("/api/v1/admin/bookings/{$booking->id}/reactivate", $payload);
});

it('reactivates a booking cancelled by hold expiry with a fresh hold', function () {
    $booking = ($this->makeBooking)();

    $this->lockService->shouldReceive('lock')->once()->withArgs(fn ($id) => (int) $id === $booking->id);

    $response = ($this->reactivate)($booking, ['hold_hours' => 6]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('booking.status', 'pending');

    $booking->refresh();
    expect($booking->status)->toBe('pending')
        ->and($booking->cancelled_at)->toBeNull()
        ->and($booking->cancellation_reason)->toBeNull()
        ->and(\Carbon\Carbon::parse($booking->reserved_until)->between(now()->addHours(6)->subMinute(), now()->addHours(6)->addMinute()))->toBeTrue();

    Mail::assertNothingQueued();
});

it('uses the default hold duration when none is given', function () {
    $booking = ($this->makeBooking)();

    ($this->reactivate)($booking)->assertOk();

    $hold = config('booking.reservation_hold_duration_hours');
    expect(\Carbon\Carbon::parse($booking->fresh()->reserved_until)->between(now()->addHours($hold)->subMinute(), now()->addHours($hold)->addMinute()))->toBeTrue();
});

it('extends the hold of a pending booking', function () {
    $booking = ($this->makeBooking)([
        'status' => 'pending',
        'reserved_until' => now()->addMinutes(10),
        'cancelled_at' => null,
        'cancellation_reason' => null,
    ]);

    ($this->reactivate)($booking, ['hold_hours' => 24])->assertOk();

    expect(\Carbon\Carbon::parse($booking->fresh()->reserved_until)->greaterThan(now()->addHours(23)))->toBeTrue();
});

it('reassigns the room unit when the original unit was taken after expiry', function () {
    $booking = ($this->makeBooking)();

    $other = Booking::factory()->create([
        'status' => 'paid',
        'booking_type' => 'overnight',
        'check_in_date' => $this->checkIn,
        'check_out_date' => $this->checkOut,
    ]);
    BookingRoom::factory()->create([
        'booking_id' => $other->id,
        'room_id' => $this->room->id,
        'room_unit_id' => $this->unit1->id,
    ]);

    ($this->reactivate)($booking)->assertOk();

    expect($booking->bookingRooms()->first()->room_unit_id)->toBe($this->unit2->id);
});

it('refuses when the room type is fully booked for the dates', function () {
    $booking = ($this->makeBooking)();

    foreach ([$this->unit1, $this->unit2] as $unit) {
        $other = Booking::factory()->create([
            'status' => 'paid',
            'booking_type' => 'overnight',
            'check_in_date' => $this->checkIn,
            'check_out_date' => $this->checkOut,
        ]);
        BookingRoom::factory()->create([
            'booking_id' => $other->id,
            'room_id' => $this->room->id,
            'room_unit_id' => $unit->id,
        ]);
    }

    ($this->reactivate)($booking)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'room_not_available');

    expect($booking->fresh()->status)->toBe('cancelled');
});

it('refuses bookings cancelled manually by an admin', function () {
    $booking = ($this->makeBooking)([
        'cancellation_reason' => config('booking.cancellation_reasons.guest_request'),
    ]);

    ($this->reactivate)($booking)
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'cannot_reactivate');

    expect($booking->fresh()->status)->toBe('cancelled');
});

it('refuses paid bookings', function () {
    $booking = ($this->makeBooking)([
        'status' => 'paid',
        'cancelled_at' => null,
        'cancellation_reason' => null,
    ]);

    ($this->reactivate)($booking)->assertStatus(422);
});

it('refuses when the check-in date has passed', function () {
    $booking = ($this->makeBooking)([
        'check_in_date' => now('Asia/Singapore')->subDays(2)->toDateString(),
        'check_out_date' => now('Asia/Singapore')->subDay()->toDateString(),
    ]);

    ($this->reactivate)($booking)->assertStatus(422);
});

it('rejects hold hours above the configured maximum', function () {
    $booking = ($this->makeBooking)();

    ($this->reactivate)($booking, ['hold_hours' => config('booking.reactivation_max_hold_hours') + 1])
        ->assertStatus(422);
});

it('queues the reservation email when notify_guest is set', function () {
    $booking = ($this->makeBooking)(['guest_email' => 'guest@example.com']);

    ($this->reactivate)($booking, ['notify_guest' => true])->assertOk();

    Mail::assertQueued(BookingReservation::class, fn ($mail) => $mail->booking->id === $booking->id);
});

it('is not available to staff', function () {
    $booking = ($this->makeBooking)();
    $staff = User::factory()->create(['role' => 'staff']);

    ($this->reactivate)($booking, [], $staff)->assertForbidden();
});

it('flags can_reactivate on the booking resource', function () {
    $expired = ($this->makeBooking)();
    $manual = ($this->makeBooking)(['cancellation_reason' => 'Other reason']);

    $show = fn (Booking $b) => $this->actingAs($this->admin)
        ->withHeader('X-TEST-USER-ID', (string) $this->admin->id)
        ->getJson("/api/v1/admin/bookings/{$b->id}");

    expect($show($expired)->json('can_reactivate'))->toBeTrue()
        ->and($show($manual)->json('can_reactivate'))->toBeFalse();
});
