<?php

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\User;

beforeEach(function () {
    $this->room = Room::factory()->create([
        'slug' => 'preview-room',
        'name' => 'Preview Room',
        'price_per_night' => 2000,
        'max_guests' => 4,
    ]);

    $this->staff = User::factory()->create([
        'role' => 'staff',
    ]);
});

it('returns balance preview for adjust nights change', function () {
    $checkIn = now()->addDays(5)->toDateString();
    $checkOut = now()->addDays(7)->toDateString();

    $booking = Booking::factory()->create([
        'status' => 'downpayment',
        'booking_type' => 'overnight',
        'check_in_date' => $checkIn,
        'check_out_date' => $checkOut,
        'final_price' => 5000,
        'discount_amount' => 0,
        'downpayment_amount' => 2500,
    ]);

    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
        'price_per_night' => 2000,
    ]);

    $newCheckOut = now()->addDays(9)->toDateString();

    $response = $this->actingAs($this->staff)
        ->withHeader('X-TEST-USER-ID', (string) $this->staff->id)
        ->postJson("/api/v1/admin/bookings/{$booking->id}/change-preview", [
            'change_type' => 'adjust_nights',
            'new_check_out_date' => $newCheckOut,
        ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'current' => [
                'net_stay_total',
                'downpayment_required',
                'amount_paid',
                'remaining_balance',
            ],
            'proposed' => [
                'net_stay_total',
                'downpayment_required',
                'delta_final',
            ],
            'proposed_downpayment_amount',
        ],
    ]);
});

it('validates change preview request fields', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'booking_type' => 'overnight',
        'check_in_date' => now()->addDays(5)->toDateString(),
        'check_out_date' => now()->addDays(7)->toDateString(),
    ]);

    BookingRoom::factory()->create([
        'booking_id' => $booking->id,
        'room_id' => $this->room->id,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
        'price_per_night' => 2000,
    ]);

    $response = $this->actingAs($this->staff)
        ->withHeader('X-TEST-USER-ID', (string) $this->staff->id)
        ->postJson("/api/v1/admin/bookings/{$booking->id}/change-preview", []);

    $response->assertStatus(422);
});
