<?php

use App\Models\Room;

test('a room model can be instantiated with attributes', function () {
    $room = new Room([
        'name' => 'Test Room',
        'description' => 'Test Description',
        'max_guests' => 5,
        'extra_guests' => 2,
        'extra_guest_fee' => 1000,
        'quantity' => 2,
        'allows_day_use' => true,
        'base_weekday_rate' => 10000,
        'base_weekend_rate' => 16000,
    ]);
    expect($room->name)->toBe('Test Room');
    expect($room->max_guests)->toBe(5);
    expect($room->extra_guest_fee)->toBe(1000);
    expect($room->extra_guest_fee)->toBe(1000);
});
