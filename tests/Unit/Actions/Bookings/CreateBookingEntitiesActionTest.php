<?php

describe('CreateBookingEntitiesAction', function () {

    beforeEach(function () {
        $this->room1 = \App\Models\Room::factory()->create(['price_per_night' => 1500]);
        $this->room2 = \App\Models\Room::factory()->create(['price_per_night' => 1800]);
        $this->bookingData = new \App\DTO\Bookings\BookingData(
            '2025-10-01',
            '14:00',
            '2025-10-03',
            '12:00',
            [
                ['room_id' => $this->room1->id, 'adults' => 2, 'children' => 1],
                ['room_id' => $this->room2->id, 'adults' => 1, 'children' => 2],
            ],
            'Guest X',
            'guestx@email.com',
            null,
            null,
            3,
            3,
            null // total adults, children
        );
        $this->roomArr = [
            (object)['room_id' => $this->room1->id, 'adults' => 2, 'children' => 1],
            (object)['room_id' => $this->room2->id, 'adults' => 1, 'children' => 2],
        ];
        $this->action = new \App\Actions\Bookings\CreateBookingEntitiesAction();
    });

    it('creates booking and booking_rooms (each line = 1 room)', function () {
        $totals = ['total_room' => 6600, 'final_price' => 9600];
        $booking = $this->action->execute($this->bookingData, $this->roomArr, 99, $totals);
        expect($booking)->toBeInstanceOf(\App\Models\Booking::class)
            ->and($booking->bookingRooms()->count())->toBe(2)
            ->and($booking->bookingRooms()->first()->adults)->toBe(2);
    });

    it('creates a unique reference_number with prefix and date', function () {
        $booking = \App\Models\Booking::create([
            // minimum required fields for your Booking
            'check_in_date' => '2025-12-01',
            'check_in_time' => '14:00',
            'check_out_date' => '2025-12-03',
            'check_out_time' => '12:00',
            'guest_name' => 'Test Ref',
            'guest_email' => 'ref@example.com',
            'total_price' => 1000,
            'final_price' => 1000,
            'status' => 'pending',
            'adults' => 1,
            'total_guests' => 1,
        ]);
        expect($booking->reference_number)->toMatch('/^NTDL-\d{6}-[A-Z0-9]{6}$/')
            ->and($booking->reference_number)->not()->toBeEmpty();
    });

    test('reference_number is unique for each booking', function () {
        $refs = [];
        foreach (range(1, 5) as $i) {
            $booking = \App\Models\Booking::create([
                'check_in_date' => '2025-12-01',
                'check_in_time' => '14:00',
                'check_out_date' => '2025-12-03',
                'check_out_time' => '12:00',
                'guest_name' => 'Test Ref ' . $i,
                'guest_email' => 'ref' . $i . '@example.com',
                'total_price' => 1000,
                'final_price' => 1000,
                'status' => 'pending',
                'adults' => 1,
                'total_guests' => 1,
            ]);
            $refs[] = $booking->reference_number;
        }
        expect(count($refs))->toBe(count(array_unique($refs)));
    });
});
