<?php

namespace Tests\Feature;

use App\Actions\Bookings\ConfirmBookingAction;
use App\Enums\RoomUnitStatusEnum;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmBookingActionTest extends TestCase
{
    use RefreshDatabase;

    private ConfirmBookingAction $confirmBookingAction;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->confirmBookingAction = app(ConfirmBookingAction::class);
        
        // Create a test room
        $this->room = Room::factory()->create([
            'name' => 'Garden View Room',
            'quantity' => 5,
        ]);
    }

    /** @test */
    public function it_assigns_room_units_on_booking_confirmation()
    {
        // Create available units
        $unit1 = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '102',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);
        
        $unit2 = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101', // Lower number, should be assigned first
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);

        // Create a booking with booking rooms
        $booking = Booking::factory()->create([
            'check_in_date' => '2024-01-01',
            'check_out_date' => '2024-01-03',
            'status' => 'pending',
        ]);
        
        $bookingRoom = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'price_per_night' => 5000,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);

        // Confirm the booking
        $result = $this->confirmBookingAction->execute($booking);

        // Assert all units were assigned
        $this->assertTrue($result);
        
        // Check that the booking room was assigned the unit with lowest number
        $bookingRoom->refresh();
        $this->assertEquals($unit2->id, $bookingRoom->room_unit_id); // Should assign unit 101
        
        // Check that the unit is now occupied
        $unit2->refresh();
        $this->assertEquals(RoomUnitStatusEnum::OCCUPIED, $unit2->status);
    }

    /** @test */
    public function it_handles_multiple_booking_rooms()
    {
        // Create available units
        $unit1 = RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '101', 'status' => RoomUnitStatusEnum::AVAILABLE]);
        $unit2 = RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '102', 'status' => RoomUnitStatusEnum::AVAILABLE]);
        $unit3 = RoomUnit::create(['room_id' => $this->room->id, 'unit_number' => '103', 'status' => RoomUnitStatusEnum::AVAILABLE]);

        // Create a booking with multiple rooms
        $booking = Booking::factory()->create([
            'check_in_date' => '2024-01-01',
            'check_out_date' => '2024-01-03',
            'status' => 'pending',
        ]);
        
        $bookingRoom1 = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'price_per_night' => 5000,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);
        
        $bookingRoom2 = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'price_per_night' => 5000,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);

        // Confirm the booking
        $result = $this->confirmBookingAction->execute($booking);

        // Assert all units were assigned
        $this->assertTrue($result);
        
        // Check that both booking rooms were assigned units
        $bookingRoom1->refresh();
        $bookingRoom2->refresh();
        
        $this->assertNotNull($bookingRoom1->room_unit_id);
        $this->assertNotNull($bookingRoom2->room_unit_id);
        $this->assertNotEquals($bookingRoom1->room_unit_id, $bookingRoom2->room_unit_id); // Different units
    }

    /** @test */
    public function it_returns_false_when_not_enough_units_available()
    {
        // Create only one available unit
        RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);

        // Create a booking with two rooms (but only one unit available)
        $booking = Booking::factory()->create([
            'check_in_date' => '2024-01-01',
            'check_out_date' => '2024-01-03',
            'status' => 'pending',
        ]);
        
        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'price_per_night' => 5000,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);
        
        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'price_per_night' => 5000,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);

        // Confirm the booking
        $result = $this->confirmBookingAction->execute($booking);

        // Should return false since not all units could be assigned
        $this->assertFalse($result);
    }

    /** @test */
    public function it_skips_already_assigned_booking_rooms()
    {
        // Create available unit
        $unit1 = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);

        // Create a booking
        $booking = Booking::factory()->create([
            'check_in_date' => '2024-01-01',
            'check_out_date' => '2024-01-03',
            'status' => 'pending',
        ]);
        
        $bookingRoom = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'room_unit_id' => $unit1->id, // Already assigned
            'price_per_night' => 5000,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);

        // Confirm the booking
        $result = $this->confirmBookingAction->execute($booking);

        // Should return true since the booking room was already assigned
        $this->assertTrue($result);
        
        // Unit should still be assigned to the same booking room
        $bookingRoom->refresh();
        $this->assertEquals($unit1->id, $bookingRoom->room_unit_id);
    }

    /** @test */
    public function it_releases_units_when_booking_is_cancelled()
    {
        // Create occupied unit
        $unit = RoomUnit::create([
            'room_id' => $this->room->id,
            'unit_number' => '101',
            'status' => RoomUnitStatusEnum::OCCUPIED,
        ]);

        // Create a booking with assigned unit
        $booking = Booking::factory()->create([
            'check_in_date' => '2024-01-01',
            'check_out_date' => '2024-01-03',
            'status' => 'paid',
        ]);
        
        $bookingRoom = BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->room->id,
            'room_unit_id' => $unit->id,
            'price_per_night' => 5000,
            'adults' => 2,
            'children' => 0,
            'total_guests' => 2,
        ]);

        // Release units for cancelled booking
        $this->confirmBookingAction->releaseUnits($booking);

        // Check that unit is now available
        $unit->refresh();
        $this->assertEquals(RoomUnitStatusEnum::AVAILABLE, $unit->status);
    }
}
