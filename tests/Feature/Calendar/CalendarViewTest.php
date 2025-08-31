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
    global $testContext;
    $testContext = $this;
    
    $this->admin = User::factory()->admin()->create();
    
    // Create a room type
    $this->room = Room::factory()->create([
        'name' => 'Deluxe Ocean View',
        'quantity' => 3,
    ]);
    
    // Create room units
    $this->units = collect();
    for ($i = 1; $i <= 3; $i++) {
        $this->units->push(
            RoomUnit::create([
                'room_id' => $this->room->id,
                'unit_number' => '10' . $i,
                'status' => RoomUnitStatusEnum::AVAILABLE
            ])
        );
    }
});

describe('Calendar Month Navigation', function () {
    it('navigates correctly between months', function () {
        // Create bookings for August and September
        createBooking('2025-08-15', '2025-08-17', 'John Doe', 'paid');
        createBooking('2025-09-05', '2025-09-07', 'Jane Smith', 'paid');
        
        // Test August data
        $augustResponse = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-01&end=2025-08-31');
        
        $augustResponse->assertOk();
        $augustData = $augustResponse->json();
        expect($augustData['events'])->toHaveCount(1);
        expect($augustData['events'][0]['guest_name'])->toBe('John Doe');
        
        // Test September data
        $septemberResponse = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-09-01&end=2025-09-30');
        
        $septemberResponse->assertOk();
        $septemberData = $septemberResponse->json();
        expect($septemberData['events'])->toHaveCount(1);
        expect($septemberData['events'][0]['guest_name'])->toBe('Jane Smith');
    });
    
    it('handles month boundaries correctly', function () {
        // Create a booking that spans from August to September
        createBooking('2025-08-30', '2025-09-02', 'Alice Brown', 'paid');
        
        // Should appear in August view
        $augustResponse = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-01&end=2025-08-31');
        
        $augustResponse->assertOk();
        $augustData = $augustResponse->json();
        expect($augustData['events'])->toHaveCount(1);
        
        // Should also appear in September view
        $septemberResponse = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-09-01&end=2025-09-30');
        
        $septemberResponse->assertOk();
        $septemberData = $septemberResponse->json();
        expect($septemberData['events'])->toHaveCount(1);
    });
});

describe('Calendar Day View', function () {
    it('shows bookings for specific day', function () {
        // Create a booking for August 15th
        createBooking('2025-08-15', '2025-08-17', 'John Doe', 'paid');
        
        // Test day view for August 15th
        $response = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-15&end=2025-08-15');
        
        $response->assertOk();
        $data = $response->json();
        
        expect($data['events'])->toHaveCount(1);
        expect($data['events'][0]['guest_name'])->toBe('John Doe');
        expect($data['events'][0]['start'])->toBe('2025-08-15');
        expect($data['events'][0]['end'])->toBe('2025-08-17');
        expect($data['events'][0]['nights'])->toBe(2);
    });
    
    it('shows empty results for day with no bookings', function () {
        // Test day view for a date with no bookings
        $response = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-20&end=2025-08-20');
        
        $response->assertOk();
        $data = $response->json();
        
        expect($data['events'])->toHaveCount(0);
        expect($data['summary'])->toHaveCount(1);
        expect($data['summary'][0]['bookings'])->toBe(0);
    });
    
    it('does not show bookings on checkout day', function () {
        // Create a booking from Aug 15-17 (checkout on Aug 17)
        createBooking('2025-08-15', '2025-08-17', 'John Doe', 'paid');
        
        // Test day view for Aug 15 (check-in day) - should show booking
        $checkInResponse = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-15&end=2025-08-15');
        
        $checkInResponse->assertOk();
        $checkInData = $checkInResponse->json();
        expect($checkInData['events'])->toHaveCount(1);
        expect($checkInData['events'][0]['guest_name'])->toBe('John Doe');
        
        // Test day view for Aug 16 (middle day) - should show booking
        $middleResponse = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-16&end=2025-08-16');
        
        $middleResponse->assertOk();
        $middleData = $middleResponse->json();
        expect($middleData['events'])->toHaveCount(1);
        expect($middleData['events'][0]['guest_name'])->toBe('John Doe');
        
        // Test day view for Aug 17 (checkout day) - should NOT show booking
        $checkOutResponse = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-17&end=2025-08-17');
        
        $checkOutResponse->assertOk();
        $checkOutData = $checkOutResponse->json();
        expect($checkOutData['events'])->toHaveCount(0);
    });
});

describe('Calendar Filtering', function () {
    it('filters by room type', function () {
        // Create another room type
        $room2 = Room::factory()->create(['name' => 'Garden View']);
        $unit2 = RoomUnit::create([
            'room_id' => $room2->id,
            'unit_number' => '201',
            'status' => RoomUnitStatusEnum::AVAILABLE
        ]);
        
        // Create bookings for different room types
        createBooking('2025-08-15', '2025-08-17', 'John Doe', 'paid');
        createBooking('2025-08-20', '2025-08-22', 'Jane Smith', 'paid', $room2->id, $unit2->id);
        
        // Test filtering by first room type
        $response = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-01&end=2025-08-31&room_type_id=' . $this->room->id);
        
        $response->assertOk();
        $data = $response->json();
        expect($data['events'])->toHaveCount(1);
        expect($data['events'][0]['guest_name'])->toBe('John Doe');
    });
    
    it('filters by status', function () {
        // Create bookings with different statuses
        createBooking('2025-08-15', '2025-08-17', 'John Doe', 'paid');
        createBooking('2025-08-20', '2025-08-22', 'Jane Smith', 'pending');
        createBooking('2025-08-25', '2025-08-27', 'Bob Johnson', 'cancelled');
        
        // Test filtering by paid status
        $response = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-01&end=2025-08-31&status=paid');
        
        $response->assertOk();
        $data = $response->json();
        expect($data['events'])->toHaveCount(1);
        expect($data['events'][0]['guest_name'])->toBe('John Doe');
        expect($data['events'][0]['status'])->toBe('paid');
    });
});

describe('Calendar Data Structure', function () {
    it('returns correct data structure', function () {
        createBooking('2025-08-15', '2025-08-17', 'John Doe', 'paid');
        
        $response = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-01&end=2025-08-31');
        
        $response->assertOk();
        $data = $response->json();
        
        // Check structure
        expect($data)->toHaveKeys(['summary', 'events']);
        expect($data['summary'])->toBeArray();
        expect($data['events'])->toBeArray();
        
        // Check event structure
        if (count($data['events']) > 0) {
            $event = $data['events'][0];
            expect($event)->toHaveKeys([
                'booking_id',
                'reference_number',
                'room_type_id',
                'room_type_name',
                'room_unit_id',
                'room_unit_number',
                'guest_name',
                'status',
                'start',
                'end',
                'nights'
            ]);
        }
        
        // Check summary structure
        if (count($data['summary']) > 0) {
            $summary = $data['summary'][0];
            expect($summary)->toHaveKeys(['date', 'bookings', 'rooms_left']);
        }
    });
    
    it('calculates occupancy correctly', function () {
        // Create multiple bookings for the same day
        createBooking('2025-08-15', '2025-08-17', 'John Doe', 'paid');
        createBooking('2025-08-15', '2025-08-16', 'Jane Smith', 'paid');
        
        $response = $this->actingAs($this->admin)
            ->withHeader('X-TEST-USER-ID', $this->admin->id)
            ->getJson('/api/v1/admin/bookings/calendar?start=2025-08-01&end=2025-08-31');
        
        $response->assertOk();
        $data = $response->json();
        
        // Find August 15th in summary
        $august15Summary = collect($data['summary'])->firstWhere('date', '2025-08-15');
        expect($august15Summary)->not->toBeNull();
        expect($august15Summary['bookings'])->toBe(2);
        expect($august15Summary['rooms_left'])->toBe(1); // 3 total - 2 booked = 1 left
    });
});

// Helper method to create bookings
function createBooking($checkIn, $checkOut, $guestName, $status, $roomId = null, $unitId = null) {
    global $testContext;
    $roomId = $roomId ?? $testContext->room->id;
    $unitId = $unitId ?? $testContext->units->random()->id;
    
    $booking = Booking::create([
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'check_in_time' => '14:00:00',
        'check_out_time' => '11:00:00',
        'guest_name' => $guestName,
        'guest_email' => strtolower(str_replace(' ', '.', $guestName)) . '@example.com',
        'status' => $status,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
        'total_price' => 300.00,
        'final_price' => 300.00
    ]);
    
    BookingRoom::create([
        'booking_id' => $booking->id,
        'room_id' => $roomId,
        'room_unit_id' => $unitId,
        'price_per_night' => 150.00,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2
    ]);
    
    return $booking;
}
