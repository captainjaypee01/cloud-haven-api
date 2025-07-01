<?php

use App\Models\User;
use App\Models\Room;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

describe('Booking API Feature', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->roomA = Room::factory()->create(['quantity' => 2, 'price_per_night' => 2000]);
        $this->roomB = Room::factory()->create(['quantity' => 1, 'price_per_night' => 1800]);
        $this->seed(\Database\Seeders\MealPriceSeeder::class);
    });

    test('user can create a booking with multiple room lines (different splits)', function () {
        $payload = [
            'check_in_date' => '2025-12-10',
            'check_in_time' => '14:00',
            'check_out_date' => '2025-12-12',
            'check_out_time' => '12:00',
            'rooms' => [
                ['room_id' => $this->roomA->id, 'adults' => 2, 'children' => 1],
                ['room_id' => $this->roomA->id, 'adults' => 1, 'children' => 2],
                ['room_id' => $this->roomB->id, 'adults' => 2, 'children' => 0],
            ],
            'guest_name' => 'Booking Test',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '09123456789',
            'special_requests' => 'Late check-in',
            'total_adults' => 5,
            'total_children' => 3,
        ];

        $response = actingAs($this->user)->postJson('/api/v1/bookings', $payload);
        $response->assertStatus(201);

        $data = $response->json();
        // Assert all rooms are created
        expect($data['booking_rooms'])->toHaveCount(3);
        // Assert final price is correct
        $roomTotal = (2000 * 2 + 1800) * 2; // (roomA*2 + roomB) * nights(2)
        $mealTotal = 5 * 1700 + 3 * 1000;   // using seeded meal prices
        $expectedFinal = $roomTotal + $mealTotal;
        expect($data['final_price'])->toBe($expectedFinal);
    });

    test('booking fails if trying to book more rooms than available', function () {
        $payload = [
            'check_in_date' => '2025-12-10',
            'check_in_time' => '14:00',
            'check_out_date' => '2025-12-12',
            'check_out_time' => '12:00',
            'rooms' => [
                ['room_id' => $this->roomB->id, 'adults' => 2, 'children' => 0],
                ['room_id' => $this->roomB->id, 'adults' => 1, 'children' => 1],
            ],
            'guest_name' => 'Booking Test',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '09123456789',
            'special_requests' => 'Late check-in',
            'total_adults' => 3,
            'total_children' => 1,
        ];

        $response = actingAs($this->user)->postJson('/api/v1/bookings', $payload);
        // Should throw/return error (409, 400, or 500 depending on exception handler)
        $response->assertStatus(409); // Or adjust to match your actual error code
    });

    test('booking fails with missing required fields', function () {
        $payload = [
            'rooms' => [],
            // No dates, no guest info
        ];

        $response = $this->actingAs($this->user)->postJson('/api/v1/bookings', $payload);
        $response->assertStatus(422);
    });

    test('booking creates correct DB records for rooms/adult/child splits', function () {
        $payload = [
            'check_in_date' => '2025-12-10',
            'check_in_time' => '14:00',
            'check_out_date' => '2025-12-11',
            'check_out_time' => '12:00',
            'rooms' => [
                ['room_id' => $this->roomA->id, 'adults' => 1, 'children' => 1],
            ],
            'guest_name' => 'Checker',
            'guest_email' => 'check@example.com',
            'total_adults' => 1,
            'total_children' => 1,
        ];

        $response = actingAs($this->user)->postJson('/api/v1/bookings', $payload);
        $response->assertStatus(201);

        $bookingId = $response->json('id');
        assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'guest_name' => 'Checker',
            'total_guests' => 2,
        ]);
        assertDatabaseHas('booking_rooms', [
            'booking_id' => $bookingId,
            'adults' => 1,
            'children' => 1,
        ]);
    });

    it('returns 409 if booking more rooms than available (RoomNotAvailableException)', function () {
        $payload = [
            'check_in_date' => '2025-12-10',
            'check_in_time' => '14:00',
            'check_out_date' => '2025-12-12',
            'check_out_time' => '12:00',
            'rooms' => [
                [ 'room_id' => $this->roomB->id, 'adults' => 2, 'children' => 0 ],
                [ 'room_id' => $this->roomB->id, 'adults' => 1, 'children' => 1 ],
            ],
            'guest_name' => 'Booking Test',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '09123456789',
            'special_requests' => 'Late check-in',
            'total_adults' => 3,
            'total_children' => 1,
        ];

        $response = actingAs($this->user)->postJson('/api/v1/bookings', $payload);
        $response->assertStatus(409)
            ->assertJson(['error' => 'Room not available for your selected dates or quantity.']);
    });
});
