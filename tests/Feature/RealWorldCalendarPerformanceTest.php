<?php

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\RoomUnitService;
use App\Enums\RoomUnitStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->roomUnitService = app(RoomUnitService::class);
    Cache::flush(); // Clear cache before each test
});

it('handles realistic production-scale data with sub-second performance', function () {
    // Create realistic production data: 10 room types with 20 units each = 200 units total
    $rooms = collect();
    for ($i = 1; $i <= 10; $i++) {
        $rooms->push(Room::factory()->create([
            'name' => "Room Type {$i}",
            'quantity' => 20,
            'room_type' => 'overnight'
        ]));
    }

    // Create 200 units (20 per room)
    $units = collect();
    foreach ($rooms as $room) {
        for ($j = 1; $j <= 20; $j++) {
            $units->push(RoomUnit::create([
                'room_id' => $room->id,
                'unit_number' => $room->id . str_pad($j, 2, '0', STR_PAD_LEFT),
                'status' => RoomUnitStatusEnum::AVAILABLE
            ]));
        }
    }

    // Create 300 bookings spread across the month (realistic booking density)
    for ($day = 1; $day <= 28; $day += 1) {
        for ($booking = 0; $booking < 10; $booking++) {
            $unit = $units->random();
            $checkIn = sprintf('2025-01-%02d', $day);
            $checkOut = sprintf('2025-01-%02d', min($day + rand(1, 5), 31));
            
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

    echo "\n\n=== REALISTIC PRODUCTION SCALE TEST ===\n";
    echo "Rooms: 10\n";
    echo "Units: 200\n";
    echo "Bookings: 300\n";
    echo "Days in month: 31\n";
    echo "Total cells: 6,200 (200 units × 31 days)\n";
    echo "==========================================\n\n";

    // Test first load (no cache)
    DB::enableQueryLog();
    $startTime = microtime(true);
    $calendarData = $this->roomUnitService->getRoomUnitCalendarData(2025, 1);
    $endTime = microtime(true);
    
    $queries = DB::getQueryLog();
    $queryCount = count($queries);
    $executionTime = ($endTime - $startTime) * 1000;
    
    echo "FIRST LOAD (No Cache):\n";
    echo "  Queries: {$queryCount}\n";
    echo "  Time: " . round($executionTime, 2) . "ms\n";
    echo "  Status: " . ($executionTime < 1000 ? "✅ PASS" : "❌ FAIL") . "\n\n";
    
    // Verify data integrity
    expect($calendarData)->toHaveKey('rooms');
    expect($calendarData['rooms'])->toHaveCount(10);
    expect($calendarData['year'])->toBe(2025);
    expect($calendarData['month'])->toBe(1);
    expect($calendarData['days'])->toHaveCount(31);

    // Test second load (with cache)
    DB::enableQueryLog();
    $startTime = microtime(true);
    $calendarData2 = $this->roomUnitService->getRoomUnitCalendarData(2025, 1);
    $endTime = microtime(true);
    
    $queries2 = DB::getQueryLog();
    $queryCount2 = count($queries2);
    $executionTime2 = ($endTime - $startTime) * 1000;
    
    echo "SECOND LOAD (With Cache):\n";
    echo "  Queries: {$queryCount2}\n";
    echo "  Time: " . round($executionTime2, 2) . "ms\n";
    echo "  Status: " . ($executionTime2 < 100 ? "✅ PASS" : "❌ FAIL") . "\n\n";
    
    // Verify cached data is identical
    expect($calendarData2)->toEqual($calendarData);

    // Test different month (no cache for that month)
    DB::enableQueryLog();
    $startTime = microtime(true);
    $calendarData3 = $this->roomUnitService->getRoomUnitCalendarData(2025, 2);
    $endTime = microtime(true);
    
    $queries3 = DB::getQueryLog();
    $queryCount3 = count($queries3);
    $executionTime3 = ($endTime - $startTime) * 1000;
    
    echo "DIFFERENT MONTH (No Cache):\n";
    echo "  Queries: {$queryCount3}\n";
    echo "  Time: " . round($executionTime3, 2) . "ms\n";
    echo "  Status: " . ($executionTime3 < 1000 ? "✅ PASS" : "❌ FAIL") . "\n\n";

    // Assert sub-second performance for all scenarios
    expect($executionTime)->toBeLessThan(1000, "First load should be under 1 second, but was {$executionTime}ms");
    expect($executionTime2)->toBeLessThan(100, "Cached load should be under 100ms, but was {$executionTime2}ms");
    expect($executionTime3)->toBeLessThan(1000, "Different month should be under 1 second, but was {$executionTime3}ms");
    
    // Assert minimal queries
    expect($queryCount)->toBeLessThan(5, "First load should use less than 5 queries, but used {$queryCount}");
    expect($queryCount2)->toBeLessThan(3, "Cached load should use minimal queries, but used {$queryCount2}");
    expect($queryCount3)->toBeLessThan(5, "Different month should use less than 5 queries, but used {$queryCount3}");
});

it('profiles database queries in detail', function () {
    // Create moderate test data
    $room = Room::factory()->create(['name' => 'Test Room', 'room_type' => 'overnight']);
    $unit = RoomUnit::create([
        'room_id' => $room->id,
        'unit_number' => '101',
        'status' => RoomUnitStatusEnum::AVAILABLE
    ]);

    // Create some bookings
    for ($i = 0; $i < 5; $i++) {
        $booking = Booking::factory()->create([
            'check_in_date' => '2025-01-15',
            'check_out_date' => '2025-01-17',
            'status' => 'paid',
            'booking_source' => 'walkin',
        ]);
        
        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'room_unit_id' => $unit->id,
            'price_per_night' => 100,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);
    }

    echo "\n\n=== QUERY PROFILING ===\n";
    
    // Enable query logging
    DB::enableQueryLog();
    $startTime = microtime(true);
    
    $calendarData = $this->roomUnitService->getRoomUnitCalendarData(2025, 1);
    
    $endTime = microtime(true);
    $queries = DB::getQueryLog();
    $executionTime = ($endTime - $startTime) * 1000;
    
    echo "Execution Time: " . round($executionTime, 2) . "ms\n";
    echo "Query Count: " . count($queries) . "\n\n";
    
    foreach ($queries as $i => $query) {
        echo "Query " . ($i + 1) . ":\n";
        echo "  SQL: " . $query['query'] . "\n";
        echo "  Bindings: " . json_encode($query['bindings']) . "\n";
        echo "  Time: " . $query['time'] . "ms\n\n";
    }
    
    expect($executionTime)->toBeLessThan(1000);
});
