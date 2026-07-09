<?php

use App\DTO\RoomNightDTO;
use App\DTO\RoomNightRoomDTO;
use App\DTO\RoomQuoteDTO;
use App\Models\Room;
use App\Services\RoomQuoteSnapshotService;

it('rebuilds shortened stay from snapshot rates on overlapping nights', function () {
    $room = Room::factory()->create(['price_per_night' => 3500]);

    $snapshot = new RoomQuoteDTO([
        new RoomNightDTO('2026-10-01', [new RoomNightRoomDTO($room->id, $room->slug, 3000)]),
        new RoomNightDTO('2026-10-02', [new RoomNightRoomDTO($room->id, $room->slug, 3000)]),
        new RoomNightDTO('2026-10-03', [new RoomNightRoomDTO($room->id, $room->slug, 3000)]),
    ], 9000);

    $bookingRoomArr = [(object) ['room_id' => $room->slug, 'adults' => 2, 'children' => 0]];

    $quote = app(RoomQuoteSnapshotService::class)->rebuildFromSnapshot(
        $snapshot,
        [$room->slug => $room],
        $bookingRoomArr,
        '2026-10-01',
        '2026-10-03',
    );

    expect($quote->totalRoom)->toBe(6000.0);
});

it('rebuilds extended stay with snapshot for original nights and live for added nights', function () {
    $room = Room::factory()->create(['price_per_night' => 3500]);

    \App\Models\RoomDailyRate::create([
        'room_id' => $room->id,
        'date' => '2026-10-03',
        'price_per_night' => 5000,
        'is_manual_override' => false,
    ]);

    $snapshot = new RoomQuoteDTO([
        new RoomNightDTO('2026-10-01', [new RoomNightRoomDTO($room->id, $room->slug, 3000)]),
        new RoomNightDTO('2026-10-02', [new RoomNightRoomDTO($room->id, $room->slug, 3000)]),
    ], 6000);

    $bookingRoomArr = [(object) ['room_id' => $room->slug, 'adults' => 2, 'children' => 0]];

    $quote = app(RoomQuoteSnapshotService::class)->rebuildFromSnapshot(
        $snapshot,
        [$room->slug => $room],
        $bookingRoomArr,
        '2026-10-01',
        '2026-10-04',
    );

    expect($quote->totalRoom)->toBe(11000.0);
});
