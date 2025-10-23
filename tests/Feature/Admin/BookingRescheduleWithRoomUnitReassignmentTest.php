<?php

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    
    // Create a room type with multiple units
    $this->room = Room::factory()->create([
        'name' => 'Pool View - Second Floor',
        'slug' => 'pool-view-second-floor'
    ]);
    
    // Create multiple room units for the same room type
    $this->roomUnit1 = RoomUnit::factory()->create([
        'room_id' => $this->room->id,
        'unit_number' => '201',
        'status' => \App\Enums\RoomUnitStatusEnum::AVAILABLE
    ]);
    
    $this->roomUnit2 = RoomUnit::factory()->create([
        'room_id' => $this->room->id,
        'unit_number' => '202',
        'status' => \App\Enums\RoomUnitStatusEnum::AVAILABLE
    ]);
    
    $this->roomUnit3 = RoomUnit::factory()->create([
        'room_id' => $this->room->id,
        'unit_number' => '203',
        'status' => \App\Enums\RoomUnitStatusEnum::AVAILABLE
    ]);
});

test('reschedule booking with room unit conflict automatically reassigns to available unit', function () {
    $this->actingAs($this->admin);
    
    // Create first booking for Oct 27-28, 2025 using unit 201
    $booking1 = Booking::factory()->create([
        'reference_number' => 'NL-251023-ZWFKDB',
        'check_in_date' => '2025-10-27',
        'check_out_date' => '2025-10-28',
        'status' => 'paid',
        'booking_type' => 'overnight'
    ]);
    
    BookingRoom::factory()->create([
        'booking_id' => $booking1->id,
        'room_id' => $this->room->id,
        'room_unit_id' => $this->roomUnit1->id, // Unit 201
        'adults' => 2,
        'children' => 0
    ]);
    
    // Create second booking for Oct 28-29, 2025 using unit 201 (conflict)
    $booking2 = Booking::factory()->create([
        'reference_number' => 'NL-251023-CATEDB',
        'check_in_date' => '2025-10-28',
        'check_out_date' => '2025-10-29',
        'status' => 'paid',
        'booking_type' => 'overnight'
    ]);
    
    BookingRoom::factory()->create([
        'booking_id' => $booking2->id,
        'room_id' => $this->room->id,
        'room_unit_id' => $this->roomUnit1->id, // Same unit 201
        'adults' => 2,
        'children' => 0
    ]);
    
    // Now reschedule booking1 to Oct 28, 2025 (conflicting with booking2)
    $response = $this->withHeader('X-TEST-USER-ID', $this->admin->id)
        ->patchJson("/api/v1/admin/bookings/{$booking1->id}/reschedule", [
            'check_in_date' => '2025-10-28',
            'check_out_date' => '2025-10-29'
        ]);
    
    $response->assertStatus(200);
    
    // Verify booking1 was rescheduled
    $booking1->refresh();
    expect($booking1->check_in_date->format('Y-m-d'))->toBe('2025-10-28');
    expect($booking1->check_out_date->format('Y-m-d'))->toBe('2025-10-29');
    
    // Verify booking1 was reassigned to a different unit (not unit 201)
    $booking1Room = $booking1->bookingRooms()->first();
    expect($booking1Room->room_unit_id)->not->toBe($this->roomUnit1->id);
    expect($booking1Room->room_unit_id)->toBeIn([$this->roomUnit2->id, $this->roomUnit3->id]);
    
    // Verify booking2 still has unit 201
    $booking2Room = $booking2->bookingRooms()->first();
    expect($booking2Room->room_unit_id)->toBe($this->roomUnit1->id);
});

test('reschedule booking fails when no alternative room units are available', function () {
    $this->actingAs($this->admin);
    
    // Create a room with only one unit
    $singleUnitRoom = Room::factory()->create([
        'name' => 'Single Unit Room',
        'slug' => 'single-unit-room'
    ]);
    
    $singleUnit = RoomUnit::factory()->create([
        'room_id' => $singleUnitRoom->id,
        'unit_number' => '101',
        'status' => \App\Enums\RoomUnitStatusEnum::AVAILABLE
    ]);
    
    // Create first booking using the only unit
    $booking1 = Booking::factory()->create([
        'reference_number' => 'NL-251023-FIRST',
        'check_in_date' => '2025-10-27',
        'check_out_date' => '2025-10-28',
        'status' => 'paid',
        'booking_type' => 'overnight'
    ]);
    
    BookingRoom::factory()->create([
        'booking_id' => $booking1->id,
        'room_id' => $singleUnitRoom->id,
        'room_unit_id' => $singleUnit->id,
        'adults' => 2,
        'children' => 0
    ]);
    
    // Create second booking using the same unit
    $booking2 = Booking::factory()->create([
        'reference_number' => 'NL-251023-SECOND',
        'check_in_date' => '2025-10-28',
        'check_out_date' => '2025-10-29',
        'status' => 'paid',
        'booking_type' => 'overnight'
    ]);
    
    BookingRoom::factory()->create([
        'booking_id' => $booking2->id,
        'room_id' => $singleUnitRoom->id,
        'room_unit_id' => $singleUnit->id,
        'adults' => 2,
        'children' => 0
    ]);
    
    // Try to reschedule booking1 to conflict with booking2
    $response = $this->withHeader('X-TEST-USER-ID', $this->admin->id)
        ->patchJson("/api/v1/admin/bookings/{$booking1->id}/reschedule", [
            'check_in_date' => '2025-10-28',
            'check_out_date' => '2025-10-29'
        ]);
    
    $response->assertStatus(422);
    $response->assertJsonStructure(['error']);
    expect($response->json('error'))->toContain('No available room units');
});

test('reschedule day tour booking with room unit conflict', function () {
    $this->actingAs($this->admin);
    
    // Create first day tour booking for Oct 27, 2025 using unit 201
    $booking1 = Booking::factory()->create([
        'reference_number' => 'NL-251023-DAY1',
        'check_in_date' => '2025-10-27',
        'check_out_date' => '2025-10-27',
        'status' => 'paid',
        'booking_type' => 'day_tour'
    ]);
    
    BookingRoom::factory()->create([
        'booking_id' => $booking1->id,
        'room_id' => $this->room->id,
        'room_unit_id' => $this->roomUnit1->id, // Unit 201
        'adults' => 2,
        'children' => 0
    ]);
    
    // Create second day tour booking for Oct 28, 2025 using unit 201
    $booking2 = Booking::factory()->create([
        'reference_number' => 'NL-251023-DAY2',
        'check_in_date' => '2025-10-28',
        'check_out_date' => '2025-10-28',
        'status' => 'paid',
        'booking_type' => 'day_tour'
    ]);
    
    BookingRoom::factory()->create([
        'booking_id' => $booking2->id,
        'room_id' => $this->room->id,
        'room_unit_id' => $this->roomUnit1->id, // Same unit 201
        'adults' => 2,
        'children' => 0
    ]);
    
    // Reschedule booking1 to Oct 28, 2025 (conflicting with booking2)
    $response = $this->withHeader('X-TEST-USER-ID', $this->admin->id)
        ->patchJson("/api/v1/admin/bookings/{$booking1->id}/reschedule", [
            'tour_date' => '2025-10-28'
        ]);
    
    $response->assertStatus(200);
    
    // Verify booking1 was rescheduled
    $booking1->refresh();
    expect($booking1->check_in_date->format('Y-m-d'))->toBe('2025-10-28');
    expect($booking1->check_out_date->format('Y-m-d'))->toBe('2025-10-28');
    
    // Verify booking1 was reassigned to a different unit
    $booking1Room = $booking1->bookingRooms()->first();
    expect($booking1Room->room_unit_id)->not->toBe($this->roomUnit1->id);
    expect($booking1Room->room_unit_id)->toBeIn([$this->roomUnit2->id, $this->roomUnit3->id]);
});

test('reschedule booking keeps same unit when it is still available', function () {
    $this->actingAs($this->admin);
    
    // Create booking for Oct 27-28, 2025 using unit 201
    $booking = Booking::factory()->create([
        'reference_number' => 'NL-251023-KEEP',
        'check_in_date' => '2025-10-27',
        'check_out_date' => '2025-10-28',
        'status' => 'paid',
        'booking_type' => 'overnight'
    ]);
    
    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $this->room->id,
        'room_unit_id' => $this->roomUnit1->id, // Unit 201
        'adults' => 2,
        'children' => 0
    ]);
    
    // Reschedule to Oct 30-31, 2025 (no conflict)
    $response = $this->withHeader('X-TEST-USER-ID', $this->admin->id)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/reschedule", [
            'check_in_date' => '2025-10-30',
            'check_out_date' => '2025-10-31'
        ]);
    
    $response->assertStatus(200);
    
    // Verify booking was rescheduled
    $booking->refresh();
    expect($booking->check_in_date->format('Y-m-d'))->toBe('2025-10-30');
    expect($booking->check_out_date->format('Y-m-d'))->toBe('2025-10-31');
    
    // Verify booking kept the same unit (no conflict, so no reassignment needed)
    $bookingRoom = $booking->bookingRooms()->first();
    expect($bookingRoom->room_unit_id)->toBe($this->roomUnit1->id);
});
