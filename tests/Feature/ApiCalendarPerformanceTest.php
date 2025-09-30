<?php

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Models\User;
use App\Enums\RoomUnitStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush(); // Clear cache before each test
    
    // Create a test user with admin role for authentication
    $this->user = User::factory()->create([
        'role' => 'admin'
    ]);
    $this->actingAs($this->user);
});

it('tests actual API endpoint performance with realistic data', function () {
    // Create realistic production data: 15 room types with 25 units each = 375 units total
    $rooms = collect();
    for ($i = 1; $i <= 15; $i++) {
        $rooms->push(Room::factory()->create([
            'name' => "Room Type {$i}",
            'quantity' => 25,
            'room_type' => 'overnight'
        ]));
    }

    // Create 375 units (25 per room)
    $units = collect();
    foreach ($rooms as $room) {
        for ($j = 1; $j <= 25; $j++) {
            $units->push(RoomUnit::create([
                'room_id' => $room->id,
                'unit_number' => $room->id . str_pad($j, 2, '0', STR_PAD_LEFT),
                'status' => RoomUnitStatusEnum::AVAILABLE
            ]));
        }
    }

    // Create 500 bookings spread across the month (realistic booking density)
    for ($day = 1; $day <= 28; $day += 1) {
        for ($booking = 0; $booking < 18; $booking++) {
            $unit = $units->random();
            $checkIn = sprintf('2025-01-%02d', $day);
            $checkOut = sprintf('2025-01-%02d', min($day + rand(1, 4), 31));
            
            $bookingModel = Booking::factory()->create([
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'status' => ['paid', 'downpayment', 'pending'][rand(0, 2)],
                'booking_source' => ['walkin', 'online'][rand(0, 1)],
            ]);
            
            BookingRoom::create([
                'booking_id' => $bookingModel->id,
                'room_id' => $unit->room_id,
                'room_unit_id' => $unit->id,
                'price_per_night' => rand(100, 500),
                'adults' => rand(1, 4),
                'children' => rand(0, 2),
                'total_guests' => rand(1, 6),
            ]);
        }
    }

    echo "\n\n=== API ENDPOINT PERFORMANCE TEST ===\n";
    echo "Rooms: 15\n";
    echo "Units: 375\n";
    echo "Bookings: 500\n";
    echo "Days in month: 31\n";
    echo "Total cells: 11,625 (375 units × 31 days)\n";
    echo "=====================================\n\n";

    // Test API endpoint with full overhead
    DB::enableQueryLog();
    $startTime = microtime(true);
    
    $response = $this->getJson('/api/v1/admin/room-units/calendar?year=2025&month=1');
    
    $endTime = microtime(true);
    $queries = DB::getQueryLog();
    $executionTime = ($endTime - $startTime) * 1000;
    
    echo "API ENDPOINT TEST:\n";
    echo "  Status Code: " . $response->getStatusCode() . "\n";
    echo "  Queries: " . count($queries) . "\n";
    echo "  Time: " . round($executionTime, 2) . "ms\n";
    echo "  Status: " . ($executionTime < 1000 ? "✅ PASS" : "❌ FAIL") . "\n\n";
    
    // Verify response
    $response->assertStatus(200);
    $response->assertJsonStructure([
        'success',
        'data' => [
            'year',
            'month',
            'days',
            'rooms' => [
                '*' => [
                    'room_id',
                    'room_name',
                    'units' => [
                        '*' => [
                            'id',
                            'unit_number',
                            'day_statuses' => [
                                '*' => [
                                    'day',
                                    'date',
                                    'status',
                                    'booking_source'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]);

    // Test cached response
    DB::enableQueryLog();
    $startTime = microtime(true);
    
    $response2 = $this->getJson('/api/v1/admin/room-units/calendar?year=2025&month=1');
    
    $endTime = microtime(true);
    $queries2 = DB::getQueryLog();
    $executionTime2 = ($endTime - $startTime) * 1000;
    
    echo "CACHED API ENDPOINT TEST:\n";
    echo "  Status Code: " . $response2->getStatusCode() . "\n";
    echo "  Queries: " . count($queries2) . "\n";
    echo "  Time: " . round($executionTime2, 2) . "ms\n";
    echo "  Status: " . ($executionTime2 < 100 ? "✅ PASS" : "❌ FAIL") . "\n\n";

    // Test different month
    DB::enableQueryLog();
    $startTime = microtime(true);
    
    $response3 = $this->getJson('/api/v1/admin/room-units/calendar?year=2025&month=2');
    
    $endTime = microtime(true);
    $queries3 = DB::getQueryLog();
    $executionTime3 = ($endTime - $startTime) * 1000;
    
    echo "DIFFERENT MONTH API TEST:\n";
    echo "  Status Code: " . $response3->getStatusCode() . "\n";
    echo "  Queries: " . count($queries3) . "\n";
    echo "  Time: " . round($executionTime3, 2) . "ms\n";
    echo "  Status: " . ($executionTime3 < 1000 ? "✅ PASS" : "❌ FAIL") . "\n\n";

    // Assert sub-second performance
    expect($executionTime)->toBeLessThan(1000, "API endpoint should be under 1 second, but was {$executionTime}ms");
    expect($executionTime2)->toBeLessThan(100, "Cached API should be under 100ms, but was {$executionTime2}ms");
    expect($executionTime3)->toBeLessThan(1000, "Different month API should be under 1 second, but was {$executionTime3}ms");
});

it('profiles API endpoint queries in detail', function () {
    // Create minimal test data
    $room = Room::factory()->create(['name' => 'Test Room', 'room_type' => 'overnight']);
    $unit = RoomUnit::create([
        'room_id' => $room->id,
        'unit_number' => '101',
        'status' => RoomUnitStatusEnum::AVAILABLE
    ]);

    echo "\n\n=== API QUERY PROFILING ===\n";
    
    // Enable query logging
    DB::enableQueryLog();
    $startTime = microtime(true);
    
    $response = $this->getJson('/api/v1/admin/room-units/calendar?year=2025&month=1');
    
    $endTime = microtime(true);
    $queries = DB::getQueryLog();
    $executionTime = ($endTime - $startTime) * 1000;
    
    echo "API Execution Time: " . round($executionTime, 2) . "ms\n";
    echo "Query Count: " . count($queries) . "\n\n";
    
    foreach ($queries as $i => $query) {
        echo "Query " . ($i + 1) . ":\n";
        echo "  SQL: " . $query['query'] . "\n";
        echo "  Bindings: " . json_encode($query['bindings']) . "\n";
        echo "  Time: " . $query['time'] . "ms\n\n";
    }
    
    $response->assertStatus(200);
    expect($executionTime)->toBeLessThan(1000);
});
