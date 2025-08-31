<?php

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Enums\RoomUnitStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();

    // Room and units
    $this->room = Room::factory()->create([
        'name' => 'Garden View Room',
        'quantity' => 3,
    ]);
    $this->u1 = RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '101', 'status' => RoomUnitStatusEnum::AVAILABLE]);
    $this->u2 = RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '102', 'status' => RoomUnitStatusEnum::AVAILABLE]);
    $this->u3 = RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '103', 'status' => RoomUnitStatusEnum::AVAILABLE]);

    // Booking A: 2 nights from 2025-09-01 to 2025-09-03 (units 101, 102)
    $this->ba = Booking::factory()->create([
        'check_in_date' => '2025-09-01',
        'check_in_time' => '14:00',
        'check_out_date' => '2025-09-03',
        'check_out_time' => '12:00',
        'status' => 'paid',
        'guest_name' => 'Jane Doe',
    ]);
    BookingRoom::create([
        'booking_id' => $this->ba->id,
        'room_id' => $this->room->id,
        'room_unit_id' => $this->u1->id,
        'price_per_night' => 5000,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);
    BookingRoom::create([
        'booking_id' => $this->ba->id,
        'room_id' => $this->room->id,
        'room_unit_id' => $this->u2->id,
        'price_per_night' => 5000,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);

    // Booking B: 1 night overlapping on 2025-09-02 to 2025-09-03 (unit 103)
    $this->bb = Booking::factory()->create([
        'check_in_date' => '2025-09-02',
        'check_in_time' => '14:00',
        'check_out_date' => '2025-09-03',
        'check_out_time' => '12:00',
        'status' => 'paid',
        'guest_name' => 'John Smith',
    ]);
    BookingRoom::create([
        'booking_id' => $this->bb->id,
        'room_id' => $this->room->id,
        'room_unit_id' => $this->u3->id,
        'price_per_night' => 5000,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);
});

it('returns summary and events within the date range', function () {
    $response = $this->actingAs($this->admin)
        ->withHeader('X-TEST-USER-ID', $this->admin->id)
        ->getJson('/api/v1/admin/bookings/calendar?start=2025-09-01&end=2025-09-03');

    $response->assertOk();
    $json = $response->json();

    expect($json)->toHaveKey('summary');
    expect($json)->toHaveKey('events');
    
    // Events should include 3 entries (2 for booking A, 1 for booking B)
    expect($json['events'])->toHaveCount(3);

    // Summary: 2025-09-01 => 1 booking (Booking A), 2025-09-02 => 2 bookings (A + B)
    $summaryMap = collect($json['summary'])->keyBy('date');
    expect($summaryMap['2025-09-01']['bookings'])->toBe(1);
    expect($summaryMap['2025-09-01']['rooms_left'])->toBe(2);
    expect($summaryMap['2025-09-02']['bookings'])->toBe(2);
    expect($summaryMap['2025-09-02']['rooms_left'])->toBe(1);
});

