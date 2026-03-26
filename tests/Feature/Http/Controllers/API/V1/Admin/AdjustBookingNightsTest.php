<?php

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\User;

beforeEach(function () {
    $this->room = Room::factory()->create([
        'slug' => 'adjust-nights-room',
        'name' => 'Adjust Nights Test Room',
        'price_per_night' => 1000,
        'max_guests' => 4,
    ]);

    $this->staff = User::factory()->create([
        'role' => 'staff',
    ]);
});

it('rejects day tour bookings', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'booking_type' => 'day_tour',
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(5)->toDateString(),
    ]);

    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
        'price_per_night' => 1000,
    ]);

    $response = $this->actingAs($this->staff)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/adjust-nights", [
            'new_check_out_date' => now()->addDays(6)->toDateString(),
        ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['error' => 'Adjust nights is only for overnight bookings.']);
});

it('rejects bookings that are not pending downpayment or paid', function () {
    $booking = Booking::factory()->create([
        'status' => 'cancelled',
        'booking_type' => 'overnight',
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(7)->toDateString(),
    ]);

    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
        'price_per_night' => 1000,
    ]);

    $response = $this->actingAs($this->staff)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/adjust-nights", [
            'new_check_out_date' => now()->addDays(8)->toDateString(),
        ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['error' => 'Only pending, downpayment, and paid bookings can be modified.']);
});

it('validates new_check_out_date is required', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'booking_type' => 'overnight',
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(7)->toDateString(),
    ]);

    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
        'price_per_night' => 1000,
    ]);

    $response = $this->actingAs($this->staff)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/adjust-nights", []);

    $response->assertStatus(422);
});

it('rejects new check-out on or before check-in', function () {
    $checkIn = now()->addDays(5)->toDateString();
    $checkOut = now()->addDays(7)->toDateString();

    $booking = Booking::factory()->create([
        'status' => 'pending',
        'booking_type' => 'overnight',
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
    ]);

    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
        'price_per_night' => 1000,
    ]);

    $response = $this->actingAs($this->staff)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/adjust-nights", [
            'new_check_out_date' => $checkIn,
        ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['error' => 'New check-out date must be after check-in date.']);
});
