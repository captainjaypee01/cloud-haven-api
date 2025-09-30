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

it('achieves sub-second performance with caching for calendar data', function () {
    // Create realistic data: 3 room types with 15 units each = 45 units total
    $rooms = collect();
    for ($i = 1; $i <= 3; $i++) {
        $rooms->push(Room::factory()->create([
            'name' => "Room Type {$i}",
            'quantity' => 15,
            'room_type' => 'overnight'
        ]));
    }

    // Create 45 units (15 per room)
    $units = collect();
    foreach ($rooms as $room) {
        for ($j = 1; $j <= 15; $j++) {
            $units->push(RoomUnit::create([
                'room_id' => $room->id,
                'unit_number' => $room->id . str_pad($j, 2, '0', STR_PAD_LEFT),
                'status' => RoomUnitStatusEnum::AVAILABLE
            ]));
        }
    }

    // Create 50 bookings spread across the month
    for ($day = 1; $day <= 28; $day += 2) {
        for ($booking = 0; $booking < 2; $booking++) {
            $unit = $units->random();
            $checkIn = sprintf('2025-01-%02d', $day);
            $checkOut = sprintf('2025-01-%02d', min($day + rand(1, 3), 31));
            
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

    echo "\n\n=== ULTRA PERFORMANCE TEST ===\n";
    echo "Rooms: 3\n";
    echo "Units: 45\n";
    echo "Bookings: 50\n";
    echo "Days in month: 31\n";
    echo "Total cells: 1,395 (45 units × 31 days)\n";
    echo "==============================\n\n";

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
    expect($calendarData['rooms'])->toHaveCount(3);
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

it('correctly handles cache invalidation when bookings change', function () {
    // Create test data
    $room = Room::factory()->create(['name' => 'Test Room', 'room_type' => 'overnight']);
    $unit = RoomUnit::create([
        'room_id' => $room->id,
        'unit_number' => '101',
        'status' => RoomUnitStatusEnum::AVAILABLE
    ]);

    // Load calendar data (should be cached)
    $calendarData1 = $this->roomUnitService->getRoomUnitCalendarData(2025, 1);
    expect($calendarData1)->toHaveKey('rooms');

    // Create a new booking
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

    // Clear cache for the affected month
    $this->roomUnitService->clearCalendarCache(2025, 1);

    // Load calendar data again (should reflect the new booking)
    $calendarData2 = $this->roomUnitService->getRoomUnitCalendarData(2025, 1);
    
    // Find the unit data
    $roomData = $calendarData2['rooms'][0];
    $unitData = collect($roomData['units'])->firstWhere('unit_number', '101');
    
    // Check Jan 15 (day 15) - should now show the booking
    $unitDay15 = collect($unitData['day_statuses'])->firstWhere('day', 15);
    
    expect($unitDay15['status'])->toBe('booked');
    expect($unitDay15['booking_source'])->toBe('walkin');
});

it('includes booking source in calendar data for walk-in bookings', function () {
    // Create test room and unit
    $room = Room::factory()->create(['name' => 'Test Room', 'room_type' => 'overnight']);
    $unit = RoomUnit::create([
        'room_id' => $room->id,
        'unit_number' => '101',
        'status' => RoomUnitStatusEnum::AVAILABLE
    ]);

    // Create walk-in booking
    $walkInBooking = Booking::factory()->create([
        'check_in_date' => '2025-01-15',
        'check_out_date' => '2025-01-17',
        'status' => 'paid',
        'booking_source' => 'walkin',
    ]);
    
    BookingRoom::create([
        'booking_id' => $walkInBooking->id,
        'room_id' => $room->id,
        'room_unit_id' => $unit->id,
        'price_per_night' => 100,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);

    // Create online booking
    $onlineBooking = Booking::factory()->create([
        'check_in_date' => '2025-01-20',
        'check_out_date' => '2025-01-22',
        'status' => 'paid',
        'booking_source' => 'online',
    ]);
    
    BookingRoom::create([
        'booking_id' => $onlineBooking->id,
        'room_id' => $room->id,
        'room_unit_id' => $unit->id,
        'price_per_night' => 100,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);

    // Get calendar data
    $calendarData = $this->roomUnitService->getRoomUnitCalendarData(2025, 1);
    
    // Find the unit data
    $roomData = $calendarData['rooms'][0];
    $unitData = collect($roomData['units'])->firstWhere('unit_number', '101');
    
    // Check walk-in booking (Jan 15-16)
    $unitDay15 = collect($unitData['day_statuses'])->firstWhere('day', 15);
    $unitDay16 = collect($unitData['day_statuses'])->firstWhere('day', 16);
    
    expect($unitDay15['status'])->toBe('booked');
    expect($unitDay15['booking_source'])->toBe('walkin');
    expect($unitDay16['status'])->toBe('booked');
    expect($unitDay16['booking_source'])->toBe('walkin');
    
    // Check online booking (Jan 20-21)
    $unitDay20 = collect($unitData['day_statuses'])->firstWhere('day', 20);
    $unitDay21 = collect($unitData['day_statuses'])->firstWhere('day', 21);
    
    expect($unitDay20['status'])->toBe('booked');
    expect($unitDay20['booking_source'])->toBe('online');
    expect($unitDay21['status'])->toBe('booked');
    expect($unitDay21['booking_source'])->toBe('online');
    
    // Check available day (Jan 10)
    $unitDay10 = collect($unitData['day_statuses'])->firstWhere('day', 10);
    expect($unitDay10['status'])->toBe('available');
    expect($unitDay10['booking_source'])->toBeNull();
});
