<?php

namespace Tests\Feature;

use App\DTO\RoomUnits\GenerateUnitsData;
use App\Enums\RoomUnitStatusEnum;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\RoomUnitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomUnitServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoomUnitService $roomUnitService;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roomUnitService = app(RoomUnitService::class);
        
        // Create a test room
        $this->room = Room::factory()->create([
            'name' => 'Garden View Room',
            'quantity' => 10,
        ]);
    }

    /** @test */
    public function it_can_generate_units_with_ranges()
    {
        $data = new GenerateUnitsData(
            ranges: [
                ['start' => 101, 'end' => 103],
                ['prefix' => 'A', 'start' => 201, 'end' => 202],
            ],
            numbers: null,
            skip_existing: false
        );

        $result = $this->roomUnitService->generateUnits($this->room, $data);

        $this->assertEquals(5, $result['total_created']);
        $this->assertEquals(0, $result['total_skipped']);
        
        // Check that units were created correctly
        $this->assertDatabaseHas('room_units', ['room_id' => $this->room->id, 'unit_number' => '101']);
        $this->assertDatabaseHas('room_units', ['room_id' => $this->room->id, 'unit_number' => '102']);
        $this->assertDatabaseHas('room_units', ['room_id' => $this->room->id, 'unit_number' => '103']);
        $this->assertDatabaseHas('room_units', ['room_id' => $this->room->id, 'unit_number' => 'A201']);
        $this->assertDatabaseHas('room_units', ['room_id' => $this->room->id, 'unit_number' => 'A202']);
    }

    /** @test */
    public function it_can_generate_units_with_manual_numbers()
    {
        $data = new GenerateUnitsData(
            ranges: null,
            numbers: ['101', '102', '201', '202A'],
            skip_existing: false
        );

        $result = $this->roomUnitService->generateUnits($this->room, $data);

        $this->assertEquals(4, $result['total_created']);
        $this->assertEquals(0, $result['total_skipped']);
        
        $this->assertDatabaseHas('room_units', ['room_id' => $this->room->id, 'unit_number' => '101']);
        $this->assertDatabaseHas('room_units', ['room_id' => $this->room->id, 'unit_number' => '202A']);
    }

    /** @test */
    public function it_can_skip_existing_units()
    {
        // Create existing unit
        RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);

        $data = new GenerateUnitsData(
            ranges: [['start' => 101, 'end' => 103]],
            numbers: null,
            skip_existing: true
        );

        $result = $this->roomUnitService->generateUnits($this->room, $data);

        $this->assertEquals(2, $result['total_created']); // Only 102 and 103
        $this->assertEquals(1, $result['total_skipped']); // 101 was skipped
        
        $this->assertDatabaseHas('room_units', ['room_id' => $this->room->id, 'unit_number' => '102']);
        $this->assertDatabaseHas('room_units', ['room_id' => $this->room->id, 'unit_number' => '103']);
    }

    /** @test */
    public function it_assigns_available_unit_to_booking()
    {
        // Create some units
        $unit1 = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '102',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);
        
        $unit2 = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101', // Lower number, should be picked first
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);

        $assignedUnit = $this->roomUnitService->assignUnitToBooking(
            $this->room->id,
            '2024-01-01',
            '2024-01-02'
        );

        $this->assertNotNull($assignedUnit);
        $this->assertEquals('101', $assignedUnit->unit_number); // Should pick lowest number
        $this->assertEquals(RoomUnitStatusEnum::OCCUPIED, $assignedUnit->status);
    }

    /** @test */
    public function it_returns_null_when_no_units_available()
    {
        // Create occupied unit
        RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::OCCUPIED,
        ]);

        $assignedUnit = $this->roomUnitService->assignUnitToBooking(
            $this->room->id,
            '2024-01-01',
            '2024-01-02'
        );

        $this->assertNull($assignedUnit);
    }

    /** @test */
    public function it_handles_concurrent_unit_assignment()
    {
        // Create a single available unit
        $unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);

        // Simulate concurrent access
        $results = [];
        
        DB::transaction(function () use (&$results) {
            // First assignment should succeed
            $results[] = $this->roomUnitService->assignUnitToBooking(
                $this->room->id,
                '2024-01-01',
                '2024-01-02'
            );
        });

        DB::transaction(function () use (&$results) {
            // Second assignment should fail (no more units)
            $results[] = $this->roomUnitService->assignUnitToBooking(
                $this->room->id,
                '2024-01-01',
                '2024-01-02'
            );
        });

        $this->assertNotNull($results[0]); // First should succeed
        $this->assertNull($results[1]);    // Second should fail
    }

    /** @test */
    public function it_prevents_deleting_assigned_units()
    {
        $unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);

        // Create a booking room with this unit
        $booking = Booking::factory()->create();
        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'room_unit_id' => $unit->id,
            'price_per_night' => 5000,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot delete room unit 101 as it\'s assigned to existing bookings');

        $this->roomUnitService->deleteRoomUnit($unit);
    }

    /** @test */
    public function it_can_delete_unassigned_units()
    {
        $unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);

        $result = $this->roomUnitService->deleteRoomUnit($unit);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('room_units', ['id' => $unit->id]);
    }

    /** @test */
    public function it_releases_unit_when_status_is_occupied()
    {
        $unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::OCCUPIED,
        ]);

        $this->roomUnitService->releaseUnit($unit);

        $unit->refresh();
        $this->assertEquals(RoomUnitStatusEnum::AVAILABLE, $unit->status);
    }

    /** @test */
    public function it_gets_availability_stats()
    {
        // Create units with different statuses
        RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '101', 'status' => RoomUnitStatusEnum::AVAILABLE]);
        RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '102', 'status' => RoomUnitStatusEnum::AVAILABLE]);
        RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '103', 'status' => RoomUnitStatusEnum::OCCUPIED]);
        RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '104', 'status' => RoomUnitStatusEnum::MAINTENANCE]);
        RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '105', 'status' => RoomUnitStatusEnum::BLOCKED]);

        $stats = $this->roomUnitService->getRoomAvailabilityStats($this->room->id);

        $expected = [
            'total' => 5,
            'available' => 2,
            'occupied' => 1,
            'maintenance' => 1,
            'blocked' => 1,
        ];

        $this->assertEquals($expected, $stats);
    }

    /** @test */
    public function it_includes_booking_source_in_calendar_data()
    {
        // Create room units
        $unit1 = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::AVAILABLE
        ]);

        $unit2 = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '102',
            'status' => RoomUnitStatusEnum::AVAILABLE
        ]);

        // Create online booking
        $onlineBooking = Booking::factory()->create([
            'check_in_date' => '2025-01-15',
            'check_out_date' => '2025-01-17',
            'status' => 'paid',
            'booking_source' => 'online',
            'guest_name' => 'Online Guest',
        ]);

        BookingRoom::create([
            'booking_id' => $onlineBooking->id,
            'room_id' => $this->room->id,
            'room_unit_id' => $unit1->id,
            'price_per_night' => 100,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);

        // Create walk-in booking
        $walkinBooking = Booking::factory()->create([
            'check_in_date' => '2025-01-15',
            'check_out_date' => '2025-01-17',
            'status' => 'paid',
            'booking_source' => 'walkin',
            'guest_name' => 'Walk-in Guest',
        ]);

        BookingRoom::create([
            'booking_id' => $walkinBooking->id,
            'room_id' => $this->room->id,
            'room_unit_id' => $unit2->id,
            'price_per_night' => 100,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);

        // Get calendar data for January 2025
        $calendarData = $this->roomUnitService->getRoomUnitCalendarData(2025, 1);

        $this->assertArrayHasKey('rooms', $calendarData);
        $this->assertCount(1, $calendarData['rooms']); // One room type

        $room = $calendarData['rooms'][0];
        $this->assertCount(2, $room['units']); // Two units

        // Find the units
        $unit1Data = collect($room['units'])->firstWhere('unit_number', '101');
        $unit2Data = collect($room['units'])->firstWhere('unit_number', '102');

        $this->assertNotNull($unit1Data);
        $this->assertNotNull($unit2Data);

        // Check January 15th status for both units
        $unit1Day15 = collect($unit1Data['day_statuses'])->firstWhere('day', 15);
        $unit2Day15 = collect($unit2Data['day_statuses'])->firstWhere('day', 15);

        $this->assertEquals('booked', $unit1Day15['status']);
        $this->assertEquals('online', $unit1Day15['booking_source']);

        $this->assertEquals('booked', $unit2Day15['status']);
        $this->assertEquals('walkin', $unit2Day15['booking_source']);

        // Check January 16th status for both units
        $unit1Day16 = collect($unit1Data['day_statuses'])->firstWhere('day', 16);
        $unit2Day16 = collect($unit2Data['day_statuses'])->firstWhere('day', 16);

        $this->assertEquals('booked', $unit1Day16['status']);
        $this->assertEquals('online', $unit1Day16['booking_source']);

        $this->assertEquals('booked', $unit2Day16['status']);
        $this->assertEquals('walkin', $unit2Day16['booking_source']);

        // Check January 17th (check-out day) - should be available
        $unit1Day17 = collect($unit1Data['day_statuses'])->firstWhere('day', 17);
        $unit2Day17 = collect($unit2Data['day_statuses'])->firstWhere('day', 17);

        $this->assertEquals('available', $unit1Day17['status']);
        $this->assertNull($unit1Day17['booking_source']);

        $this->assertEquals('available', $unit2Day17['status']);
        $this->assertNull($unit2Day17['booking_source']);
    }
}
