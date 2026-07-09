<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomDailyRate;
use App\Models\RoomUnit;
use App\Models\User;
use App\Enums\RoomUnitStatusEnum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->room = Room::factory()->create([
        'price_per_night' => 3500,
        'room_type' => 'overnight',
        'max_guests' => 6,
        'extra_guests' => 2,
        'quantity' => 3,
    ]);
    $this->roomUnit = RoomUnit::factory()->create([
        'room_id' => $this->room->id,
        'status' => RoomUnitStatusEnum::AVAILABLE,
    ]);
    $this->seed(\Database\Seeders\MealPriceSeeder::class);
});

function asAdmin(User $admin)
{
    return test()->actingAs($admin)->withHeader('X-TEST-USER-ID', (string) $admin->id);
}

it('returns calendar month with default and explicit rates', function () {
    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-08-15',
        'price_per_night' => 5000,
        'is_manual_override' => true,
    ]);

    $response = asAdmin($this->admin)
        ->getJson("/api/v1/admin/rooms/{$this->room->id}/pricing/calendar?month=2026-08");

    $response->assertOk();
    $days = $response->json('days');
    $aug15 = collect($days)->firstWhere('date', '2026-08-15');
    expect((float) $aug15['price_per_night'])->toBe(5000.0);
    expect($aug15['is_manual_override'])->toBeTrue();
});

it('bulk updates calendar and preview respects pinned days', function () {
    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-08-20',
        'price_per_night' => 9000,
        'is_manual_override' => true,
    ]);

    $preview = asAdmin($this->admin)
        ->postJson("/api/v1/admin/rooms/{$this->room->id}/pricing/calendar/preview", [
            'mode' => 'flat',
            'from' => '2026-08-18',
            'to' => '2026-08-22',
            'price' => 4000,
            'skip_manual_overrides' => true,
        ]);

    $preview->assertOk();
    expect($preview->json('would_skip'))->toBe(1);
    expect($preview->json('would_update'))->toBe(4);
});

it('overnight quote uses calendar rates', function () {
    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-09-01',
        'price_per_night' => 8000,
        'is_manual_override' => false,
    ]);
    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-09-02',
        'price_per_night' => 9000,
        'is_manual_override' => false,
    ]);

    $response = $this->postJson('/api/v1/quotes/overnight', [
        'check_in_date' => '2026-09-01',
        'check_out_date' => '2026-09-03',
        'rooms' => [
            ['room_id' => $this->room->slug, 'adults' => 2, 'children' => 0],
        ],
    ]);

    $response->assertOk();
    expect((float) $response->json('total_room'))->toBe(17000.0);
});

it('modifying booking guests keeps snapshot room rates', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'booking_type' => 'overnight',
        'check_in_date' => '2026-10-01',
        'check_out_date' => '2026-10-03',
        'total_price' => 6000,
        'final_price' => 6000,
        'room_quote_data' => json_encode([
            'nights' => [
                ['date' => '2026-10-01', 'rooms' => [['room_id' => $this->room->id, 'slug' => $this->room->slug, 'rate' => 3000]]],
                ['date' => '2026-10-02', 'rooms' => [['room_id' => $this->room->id, 'slug' => $this->room->slug, 'rate' => 3000]]],
            ],
            'total_room' => 6000,
        ]),
        'meal_quote_data' => json_encode(['nights' => []]),
    ]);

    $booking->bookingRooms()->create([
        'room_id' => $this->room->id,
        'room_unit_id' => $this->roomUnit->id,
        'price_per_night' => 3000,
        'total_price' => 6000,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);

    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-10-01',
        'price_per_night' => 99999,
        'is_manual_override' => false,
    ]);

    $response = asAdmin($this->admin)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/modify", [
            'rooms' => [[
                'room_id' => $this->room->slug,
                'adults' => 3,
                'children' => 0,
                'total_guests' => 3,
            ]],
            'modification_reason' => 'Guest count change',
            'send_email' => false,
        ]);

    $response->assertOk();
    $booking->refresh();
    expect((float) $booking->total_price)->toBe(6000.0);
});

it('shortening stay keeps snapshot rates on remaining nights', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'booking_type' => 'overnight',
        'check_in_date' => '2026-10-01',
        'check_out_date' => '2026-10-04',
        'total_price' => 9000,
        'final_price' => 9000,
        'room_quote_data' => json_encode([
            'nights' => [
                ['date' => '2026-10-01', 'rooms' => [['room_id' => $this->room->id, 'slug' => $this->room->slug, 'rate' => 3000]]],
                ['date' => '2026-10-02', 'rooms' => [['room_id' => $this->room->id, 'slug' => $this->room->slug, 'rate' => 3000]]],
                ['date' => '2026-10-03', 'rooms' => [['room_id' => $this->room->id, 'slug' => $this->room->slug, 'rate' => 3000]]],
            ],
            'total_room' => 9000,
        ]),
        'meal_quote_data' => json_encode(['nights' => []]),
    ]);

    $booking->bookingRooms()->create([
        'room_id' => $this->room->id,
        'room_unit_id' => $this->roomUnit->id,
        'price_per_night' => 3000,
        'total_price' => 9000,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);

    foreach (['2026-10-01', '2026-10-02', '2026-10-03'] as $date) {
        RoomDailyRate::create([
            'room_id' => $this->room->id,
            'date' => $date,
            'price_per_night' => 99999,
            'is_manual_override' => false,
        ]);
    }

    $response = asAdmin($this->admin)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/adjust-nights", [
            'new_check_out_date' => '2026-10-03',
        ]);

    $response->assertOk();
    $booking->refresh();
    expect((float) $booking->total_price)->toBe(6000.0);
    expect((float) $booking->bookingRooms->first()->total_price)->toBe(6000.0);
});

it('extending stay uses snapshot for original nights and live rates for new nights', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'booking_type' => 'overnight',
        'check_in_date' => '2026-10-01',
        'check_out_date' => '2026-10-03',
        'total_price' => 6000,
        'final_price' => 6000,
        'room_quote_data' => json_encode([
            'nights' => [
                ['date' => '2026-10-01', 'rooms' => [['room_id' => $this->room->id, 'slug' => $this->room->slug, 'rate' => 3000]]],
                ['date' => '2026-10-02', 'rooms' => [['room_id' => $this->room->id, 'slug' => $this->room->slug, 'rate' => 3000]]],
            ],
            'total_room' => 6000,
        ]),
        'meal_quote_data' => json_encode(['nights' => []]),
    ]);

    $booking->bookingRooms()->create([
        'room_id' => $this->room->id,
        'room_unit_id' => $this->roomUnit->id,
        'price_per_night' => 3000,
        'total_price' => 6000,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);

    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-10-01',
        'price_per_night' => 99999,
        'is_manual_override' => false,
    ]);
    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-10-02',
        'price_per_night' => 99999,
        'is_manual_override' => false,
    ]);
    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-10-03',
        'price_per_night' => 5000,
        'is_manual_override' => false,
    ]);

    $response = asAdmin($this->admin)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/adjust-nights", [
            'new_check_out_date' => '2026-10-04',
        ]);

    $response->assertOk();
    $booking->refresh();
    expect((float) $booking->total_price)->toBe(11000.0);
});

it('reschedule with no overlapping dates uses live calendar rates', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'booking_type' => 'overnight',
        'check_in_date' => '2026-10-01',
        'check_out_date' => '2026-10-03',
        'total_price' => 6000,
        'final_price' => 6000,
        'room_quote_data' => json_encode([
            'nights' => [
                ['date' => '2026-10-01', 'rooms' => [['room_id' => $this->room->id, 'slug' => $this->room->slug, 'rate' => 3000]]],
                ['date' => '2026-10-02', 'rooms' => [['room_id' => $this->room->id, 'slug' => $this->room->slug, 'rate' => 3000]]],
            ],
            'total_room' => 6000,
        ]),
        'meal_quote_data' => json_encode(['nights' => []]),
    ]);

    $booking->bookingRooms()->create([
        'room_id' => $this->room->id,
        'room_unit_id' => $this->roomUnit->id,
        'price_per_night' => 3000,
        'total_price' => 6000,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);

    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-10-15',
        'price_per_night' => 4500,
        'is_manual_override' => false,
    ]);
    RoomDailyRate::create([
        'room_id' => $this->room->id,
        'date' => '2026-10-16',
        'price_per_night' => 4500,
        'is_manual_override' => false,
    ]);

    $response = asAdmin($this->admin)
        ->patchJson("/api/v1/admin/bookings/{$booking->id}/reschedule", [
            'check_in_date' => '2026-10-15',
            'check_out_date' => '2026-10-17',
        ]);

    $response->assertOk();
    $booking->refresh();
    expect((float) $booking->total_price)->toBe(9000.0);
});
