<?php
// tests/Unit/Actions/CalculateBookingTotalActionTest.php
describe('CalculateBookingTotalAction with BookingData totals', function () {
    beforeEach(function () {
        $this->mockMealPriceService = mock(\App\Contracts\Services\MealPriceServiceInterface::class);
        $this->mockMealPriceService->shouldReceive('getPriceForCategory')->with('adult')->andReturn(1500);
        $this->mockMealPriceService->shouldReceive('getPriceForCategory')->with('children')->andReturn(900);
        $this->action = new \App\Actions\Bookings\CalculateBookingTotalAction($this->mockMealPriceService);
    });

    it('calculates totals for multiple rooms, sums meal prices from booking data', function () {
        $room1 = \App\Models\Room::factory()->create(['price_per_night' => 2000]);
        $room2 = \App\Models\Room::factory()->create(['price_per_night' => 1800]);
        $bookingRoomArr = [
            (object)['room_id' => $room1->id, 'adults' => 2, 'children' => 1],
            (object)['room_id' => $room2->id, 'adults' => 2, 'children' => 2],
        ];
        $check_in = '2025-07-01';
        $check_out = '2025-07-04';
        $nights = 3;
        $totalAdults = 4;   // sum from both rooms
        $totalChildren = 3; // sum from both rooms

        $result = $this->action->execute($bookingRoomArr, $check_in, $check_out, $totalAdults, $totalChildren);

        $expectedRoomTotal = (float) ($room1->price_per_night + $room2->price_per_night) * $nights;
        $expectedMealTotal = floatval(4 * 1500 + 3 * 900);
        $expectedFinal = (float) ($expectedRoomTotal + $expectedMealTotal);

        expect($result['total_room'])->toBe($expectedRoomTotal)
            ->and($result['meal_total'])->toBe($expectedMealTotal)
            ->and($result['final_price'])->toBe($expectedFinal);
    });

    it('calculates zero meal total when no adults or children', function () {
        $room = \App\Models\Room::factory()->create(['price_per_night' => 1000]);
        $bookingRoomArr = [(object)['room_id' => $room->id, 'adults' => 0, 'children' => 0]];
        $check_in = '2025-07-01';
        $check_out = '2025-07-02';
        $nights = 1;

        $result = $this->action->execute($bookingRoomArr, $check_in, $check_out, 0, 0);
        expect($result['total_room'])->toBe(1000.0)
            ->and($result['meal_total'])->toBe(0.0)
            ->and($result['final_price'])->toBe(1000.0);
    });

    it('handles multiple identical rooms with different guest splits', function () {
        $room = \App\Models\Room::factory()->create(['price_per_night' => 1200]);
        $bookingRoomArr = [
            (object)['room_id' => $room->id, 'adults' => 2, 'children' => 0],
            (object)['room_id' => $room->id, 'adults' => 1, 'children' => 1],
            (object)['room_id' => $room->id, 'adults' => 2, 'children' => 2],
        ];
        $check_in = '2025-07-01';
        $check_out = '2025-07-04';
        $nights = 3;
        $totalAdults = 5;
        $totalChildren = 3;

        $mockMealPriceService = Mockery::mock(\App\Contracts\Services\MealPriceServiceInterface::class);
        $mockMealPriceService->shouldReceive('getPriceForCategory')->with('adult')->andReturn(1000);
        $mockMealPriceService->shouldReceive('getPriceForCategory')->with('children')->andReturn(500);
        $action = new \App\Actions\Bookings\CalculateBookingTotalAction($mockMealPriceService);

        $result = $action->execute($bookingRoomArr, $check_in, $check_out, $totalAdults, $totalChildren);
        $expectedRoomTotal = floatval(3 * 1200 * 3); // 3 rooms, 3 nights, 1200 per night
        $expectedMealTotal = floatval((5 * 1000) + (3 * 500));
        $expectedFinal = $expectedRoomTotal + $expectedMealTotal;

        expect($result['total_room'])->toBe($expectedRoomTotal)
            ->and($result['meal_total'])->toBe($expectedMealTotal)
            ->and($result['final_price'])->toBe($expectedFinal);
    });

    it('calculates correctly for adults only', function () {
        $room = \App\Models\Room::factory()->create(['price_per_night' => 2500]);
        $bookingRoomArr = [(object)['room_id' => $room->id, 'adults' => 4, 'children' => 0]];
        $check_in = '2025-08-10';
        $check_out = '2025-08-12';
        $nights = 2;

        $mockMealPriceService = Mockery::mock(\App\Contracts\Services\MealPriceServiceInterface::class);
        $mockMealPriceService->shouldReceive('getPriceForCategory')->with('adult')->andReturn(1800);
        $mockMealPriceService->shouldReceive('getPriceForCategory')->with('children')->andReturn(0);
        $action = new \App\Actions\Bookings\CalculateBookingTotalAction($mockMealPriceService);

        $result = $action->execute($bookingRoomArr, $check_in, $check_out, 4, 0);
        $expectedRoomTotal = floatval(2500 * 2);
        $expectedMealTotal = floatval(4 * 1800);
        $expectedFinal = $expectedRoomTotal + $expectedMealTotal;

        expect($result['total_room'])->toBe($expectedRoomTotal)
            ->and($result['meal_total'])->toBe($expectedMealTotal)
            ->and($result['final_price'])->toBe($expectedFinal);
    });

    it('calculates correctly for children only', function () {
        $room = \App\Models\Room::factory()->create(['price_per_night' => 800]);
        $bookingRoomArr = [(object)['room_id' => $room->id, 'adults' => 0, 'children' => 3]];
        $check_in = '2025-09-01';
        $check_out = '2025-09-05';
        $nights = 4;

        $mockMealPriceService = Mockery::mock(\App\Contracts\Services\MealPriceServiceInterface::class);
        $mockMealPriceService->shouldReceive('getPriceForCategory')->with('adult')->andReturn(0);
        $mockMealPriceService->shouldReceive('getPriceForCategory')->with('children')->andReturn(400);
        $action = new \App\Actions\Bookings\CalculateBookingTotalAction($mockMealPriceService);

        $result = $action->execute($bookingRoomArr, $check_in, $check_out, 0, 3);
        $expectedRoomTotal = floatval(800 * 4);
        $expectedMealTotal = floatval(3 * 400);
        $expectedFinal = $expectedRoomTotal + $expectedMealTotal;

        expect($result['total_room'])->toBe($expectedRoomTotal)
            ->and($result['meal_total'])->toBe($expectedMealTotal)
            ->and($result['final_price'])->toBe($expectedFinal);
    });

    it('returns zero totals when no rooms', function () {
        $mockMealPriceService = Mockery::mock(\App\Contracts\Services\MealPriceServiceInterface::class);
        $mockMealPriceService->shouldReceive('getPriceForCategory')->with('adult')->andReturn(1200);
        $mockMealPriceService->shouldReceive('getPriceForCategory')->with('children')->andReturn(800);
        $action = new \App\Actions\Bookings\CalculateBookingTotalAction($mockMealPriceService);

        $result = $action->execute([], '2025-11-01', '2025-11-03', 0, 0);
        expect($result['total_room'])->toBe(0)
            ->and($result['meal_total'])->toBe(0.0)
            ->and($result['final_price'])->toBe(0.0);
    });
});
