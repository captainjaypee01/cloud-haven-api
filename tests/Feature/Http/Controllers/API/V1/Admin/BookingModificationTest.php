<?php

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\User;

beforeEach(function () {
    // Create test rooms
    $this->room1 = Room::factory()->create([
        'slug' => 'test-room-1',
        'name' => 'Test Room 1',
        'price_per_night' => 1000,
        'max_guests' => 4,
    ]);
    
    $this->room2 = Room::factory()->create([
        'slug' => 'test-room-2',
        'name' => 'Test Room 2',
        'price_per_night' => 1500,
        'max_guests' => 6,
    ]);
    
    // Create test user
    $this->user = User::factory()->create([
        'role' => 'admin'
    ]);
});

it('can modify booking rooms', function () {
    // Create a booking
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'check_in_date' => now()->addDays(7)->toDateString(),
        'check_out_date' => now()->addDays(9)->toDateString(),
        'total_price' => 2000,
        'final_price' => 2000,
    ]);

    // Add booking room
    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $this->room1->id,
        'adults' => 2,
        'children' => 1,
        'total_guests' => 3,
        'price_per_night' => 1000,
    ]);

    $modificationData = [
        'rooms' => [
            [
                'room_id' => $this->room2->slug,
                'adults' => 3,
                'children' => 2,
                'total_guests' => 5,
                'room_unit_id' => null // Optional room unit assignment
            ]
        ],
        'modification_reason' => 'Guest requested room change'
    ];

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/modify", $modificationData);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            'id',
            'reference_number',
            'total_price',
            'final_price',
            'booking_rooms'
        ]
    ]);

    // Verify booking was updated
    $booking->refresh();
    expect($booking->total_price)->toBe(1500.0);
    expect($booking->adults)->toBe(3);
    expect($booking->children)->toBe(2);
    expect($booking->total_guests)->toBe(5);

    // Verify booking room was updated
    expect($booking->bookingRooms)->toHaveCount(1);
    $bookingRoom = $booking->bookingRooms->first();
    expect($bookingRoom->room_id)->toBe($this->room2->id);
    expect($bookingRoom->adults)->toBe(3);
    expect($bookingRoom->children)->toBe(2);
    expect($bookingRoom->total_guests)->toBe(5);
});

it('cannot modify paid booking', function () {
    $booking = Booking::factory()->create([
        'status' => 'paid',
    ]);

    $modificationData = [
        'rooms' => [
            [
                'room_id' => $this->room1->slug,
                'adults' => 2,
                'children' => 1,
                'total_guests' => 3,
                'room_unit_id' => null
            ]
        ]
    ];

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/modify", $modificationData);

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Only pending and downpayment bookings can be modified.'
    ]);
});

it('validation requires at least one room', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
    ]);

    $modificationData = [
        'rooms' => []
    ];

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/modify", $modificationData);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['rooms']);
});

it('validation requires valid room id', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
    ]);

    $modificationData = [
        'rooms' => [
            [
                'room_id' => 'invalid-room-slug',
                'adults' => 2,
                'children' => 1,
                'total_guests' => 3,
                'room_unit_id' => null
            ]
        ]
    ];

    $response = $this->actingAs($this->user)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/modify", $modificationData);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['rooms.0.room_id']);
});
