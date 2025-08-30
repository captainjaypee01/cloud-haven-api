<?php

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Room Availability API', function () {
    beforeEach(function () {
        // Create a test room
        $this->room = Room::factory()->create([
            'slug' => 'test-room',
            'name' => 'Test Room',
            'quantity' => 5,
            'price' => 1000,
            'status' => 'active'
        ]);
    });

    it('can check room availability with no bookings', function () {
    $checkIn = Carbon::now()->addDays(1)->format('Y-m-d');
    $checkOut = Carbon::now()->addDays(3)->format('Y-m-d');

    $response = $this->getJson("/api/v1/rooms/{$this->room->slug}/availability", [
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'room_type_id',
                'room_name',
                'available_units',
                'check_in',
                'check_out'
            ]
        ])
        ->assertJson([
            'data' => [
                'room_type_id' => $this->room->slug,
                'room_name' => $this->room->name,
                'available_units' => 5,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ]
        ]);
    });

    it('calculates availability with existing bookings', function () {
    $checkIn = Carbon::now()->addDays(1)->format('Y-m-d');
    $checkOut = Carbon::now()->addDays(3)->format('Y-m-d');

    // Create a booking that overlaps with our check dates
    $booking = Booking::factory()->create([
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'status' => 'paid'
    ]);

    // Create booking rooms (2 units booked)
    $booking->bookingRooms()->create([
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
    ]);
    $booking->bookingRooms()->create([
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
    ]);

    $response = $this->getJson("/api/v1/rooms/{$this->room->slug}/availability", [
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'room_type_id' => $this->room->slug,
                'room_name' => $this->room->name,
                'available_units' => 3, // 5 total - 2 booked = 3 available
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ]
        ]);
    });

    it('returns zero availability when fully booked', function () {
    $checkIn = Carbon::now()->addDays(1)->format('Y-m-d');
    $checkOut = Carbon::now()->addDays(3)->format('Y-m-d');

    // Create bookings that use all available units
    for ($i = 0; $i < 5; $i++) {
        $booking = Booking::factory()->create([
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'status' => 'paid'
        ]);

        $booking->bookingRooms()->create([
            'room_id' => $this->room->id,
            'adults' => 2,
            'children' => 0,
        ]);
    }

    $response = $this->getJson("/api/v1/rooms/{$this->room->slug}/availability", [
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'available_units' => 0,
            ]
        ]);
    });

    it('handles partial date overlap', function () {
    $checkIn = Carbon::now()->addDays(1)->format('Y-m-d');
    $checkOut = Carbon::now()->addDays(3)->format('Y-m-d');

    // Create a booking that partially overlaps (checkout on our checkin date)
    $booking = Booking::factory()->create([
        'check_in_date' => Carbon::now()->subDays(1)->format('Y-m-d'),
        'check_out_date' => $checkIn, // Checking out when we're checking in
        'status' => 'paid'
    ]);

    $booking->bookingRooms()->create([
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
    ]);

    $response = $this->getJson("/api/v1/rooms/{$this->room->slug}/availability", [
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'available_units' => 5, // Should be fully available since checkout is on checkin date
            ]
        ]);
    });

    it('requires check in and check out dates', function () {
    $response = $this->getJson("/api/v1/rooms/{$this->room->slug}/availability");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['check_in', 'check_out']);
});

    it('validates check out after check in', function () {
    $checkIn = Carbon::now()->addDays(3)->format('Y-m-d');
    $checkOut = Carbon::now()->addDays(1)->format('Y-m-d'); // Before check-in

    $response = $this->getJson("/api/v1/rooms/{$this->room->slug}/availability", [
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['check_out']);
});

    it('returns 404 for nonexistent room', function () {
    $checkIn = Carbon::now()->addDays(1)->format('Y-m-d');
    $checkOut = Carbon::now()->addDays(3)->format('Y-m-d');

    $response = $this->getJson("/api/v1/rooms/nonexistent-room/availability", [
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Room not found.'
        ]);
    });

    it('ignores pending bookings without locks', function () {
    $checkIn = Carbon::now()->addDays(1)->format('Y-m-d');
    $checkOut = Carbon::now()->addDays(3)->format('Y-m-d');

    // Create a pending booking (should be ignored unless locked in Redis)
    $booking = Booking::factory()->create([
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'status' => 'pending'
    ]);

    $booking->bookingRooms()->create([
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
    ]);

    $response = $this->getJson("/api/v1/rooms/{$this->room->slug}/availability", [
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'available_units' => 5, // Should be fully available since pending booking isn't locked
            ]
        ]);
    });
});
