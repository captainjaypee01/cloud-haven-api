<?php

use App\Models\Room;
use App\Models\RoomDailyRate;
use App\Services\RoomPricingService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = app(RoomPricingService::class);
});

it('resolves rate from daily calendar', function () {
    $room = Room::factory()->create(['price_per_night' => 3000, 'room_type' => 'overnight']);
    $date = Carbon::parse('2026-08-15');

    RoomDailyRate::create([
        'room_id' => $room->id,
        'date' => $date->format('Y-m-d'),
        'price_per_night' => 5000,
        'is_manual_override' => false,
    ]);

    expect($this->service->getRateForDate($room, $date))->toBe(5000.0);
});

it('falls back to room default when no daily rate', function () {
    $room = Room::factory()->create(['price_per_night' => 3000, 'room_type' => 'overnight']);

    expect($this->service->getRateForDate($room, Carbon::parse('2026-09-01')))->toBe(3000.0);
});

it('bulk flat upsert updates all days in range', function () {
    $room = Room::factory()->create(['price_per_night' => 1000, 'room_type' => 'overnight']);

    $result = $this->service->bulkUpsertFlat($room->id, '2026-08-01', '2026-08-05', 10000);

    expect($result['updated'])->toBe(5);
    expect(RoomDailyRate::where('room_id', $room->id)->count())->toBe(5);
});

it('skips manual overrides on weekday bulk apply', function () {
    $room = Room::factory()->create(['price_per_night' => 1000, 'room_type' => 'overnight']);

    RoomDailyRate::create([
        'room_id' => $room->id,
        'date' => '2026-08-01',
        'price_per_night' => 20000,
        'is_manual_override' => true,
    ]);

    $this->service->bulkUpsertWeekdayPattern($room->id, '2026-08-01', '2026-08-07', [
        'fri' => 15000,
        'sat' => 15000,
        'sun' => 10000,
        'mon' => 13000,
        'tue' => 12500,
        'wed' => 13000,
        'thu' => 13000,
    ]);

    $pinned = RoomDailyRate::where('room_id', $room->id)->whereDate('date', '2026-08-01')->first();
    expect((float) $pinned->price_per_night)->toBe(20000.0);
});

it('builds stay quote with varying nightly rates', function () {
    $room = Room::factory()->create(['slug' => 'deluxe-test', 'price_per_night' => 1000, 'room_type' => 'overnight']);

    RoomDailyRate::create(['room_id' => $room->id, 'date' => '2026-08-12', 'price_per_night' => 13000, 'is_manual_override' => false]);
    RoomDailyRate::create(['room_id' => $room->id, 'date' => '2026-08-13', 'price_per_night' => 15000, 'is_manual_override' => false]);

    $quote = $this->service->buildQuoteForStay(
        [$room->slug => $room],
        [(object) ['room_id' => $room->slug, 'adults' => 2, 'children' => 0]],
        '2026-08-12',
        '2026-08-14'
    );

    expect($quote->totalRoom)->toBe(28000.0);
    expect($quote->nights)->toHaveCount(2);
});
