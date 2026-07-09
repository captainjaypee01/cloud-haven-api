<?php

use App\Actions\Bookings\CreateBookingEntitiesAction;
use App\Actions\Bookings\PersistBookingRoomNightlyRatesAction;
use App\Contracts\Services\RoomPricingServiceInterface;
use App\DTO\Bookings\BookingData;
use App\Models\Booking;
use App\Models\BookingRoomNightlyRate;
use App\Models\Room;

it('persists nightly rates for duplicate same-room lines by index', function () {
    $room = Room::factory()->create([
        'slug' => 'garden-view-ground-floor',
        'price_per_night' => 15000,
        'quantity' => 5,
    ]);

    $checkIn = '2026-07-16';
    $checkOut = '2026-07-20';

    $roomArr = [
        (object) ['room_id' => $room->slug, 'adults' => 8, 'children' => 0],
        (object) ['room_id' => $room->slug, 'adults' => 6, 'children' => 2],
    ];

    $roomQuote = app(RoomPricingServiceInterface::class)->buildQuoteForStay(
        [$room->slug => $room],
        $roomArr,
        $checkIn,
        $checkOut
    );

    $bookingData = new BookingData(
        check_in_date: $checkIn,
        check_out_date: $checkOut,
        rooms: [],
        guest_name: 'John Paul Dala',
        guest_email: 'guest@example.com',
        guest_phone: '88943684',
        special_requests: null,
        total_adults: 14,
        total_children: 2,
    );

    $totals = [
        'total_room' => $roomQuote->totalRoom,
        'meal_total' => 0,
        'extra_guest_fee' => 0,
        'extra_guest_count' => 0,
        'final_price' => $roomQuote->totalRoom,
        'room_quote' => $roomQuote,
    ];

    $action = app(CreateBookingEntitiesAction::class);
    $booking = $action->execute($bookingData, $roomArr, null, $totals);

    expect($booking->bookingRooms)->toHaveCount(2);

    $nightlyRates = BookingRoomNightlyRate::where('booking_id', $booking->id)->get();
    expect($nightlyRates)->toHaveCount(8);

    $bookingRoomIds = $booking->bookingRooms->sortBy('id')->pluck('id')->all();
    foreach ($bookingRoomIds as $bookingRoomId) {
        expect(
            $nightlyRates->where('booking_room_id', $bookingRoomId)->count()
        )->toBe(4);
    }

    expect((float) $booking->bookingRooms->sortBy('id')->first()->total_price)->toBe(60000.0)
        ->and((float) $booking->bookingRooms->sortBy('id')->last()->total_price)->toBe(60000.0);
});

it('maps nightly rate rows to booking rooms in cart line order', function () {
    $room = Room::factory()->create(['price_per_night' => 1000, 'quantity' => 3]);

    $booking = Booking::factory()->create([
        'check_in_date' => '2026-07-16',
        'check_out_date' => '2026-07-18',
    ]);

    $first = $booking->bookingRooms()->create([
        'room_id' => $room->id,
        'price_per_night' => 1000,
        'total_price' => 2000,
        'adults' => 2,
        'children' => 0,
        'total_guests' => 2,
    ]);

    $second = $booking->bookingRooms()->create([
        'room_id' => $room->id,
        'price_per_night' => 1000,
        'total_price' => 2000,
        'adults' => 4,
        'children' => 0,
        'total_guests' => 4,
    ]);

    $roomQuote = app(RoomPricingServiceInterface::class)->buildQuoteForStay(
        [$room->slug => $room],
        [
            (object) ['room_id' => $room->slug, 'adults' => 2, 'children' => 0],
            (object) ['room_id' => $room->slug, 'adults' => 4, 'children' => 0],
        ],
        '2026-07-16',
        '2026-07-18'
    );

    app(PersistBookingRoomNightlyRatesAction::class)->execute($booking, $roomQuote);

    expect(BookingRoomNightlyRate::where('booking_room_id', $first->id)->count())->toBe(2)
        ->and(BookingRoomNightlyRate::where('booking_room_id', $second->id)->count())->toBe(2);
});
