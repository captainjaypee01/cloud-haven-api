<?php

use App\Actions\Bookings\CalculateBookingTotalAction;
use App\Actions\ComputeMealQuoteAction;
use App\Contracts\Services\RoomPricingServiceInterface;
use App\DTO\MealNightDTO;
use App\DTO\MealQuoteDTO;
use App\DTO\RoomQuoteDTO;
use App\Models\Promo;
use App\Models\Room;
use App\Services\PromoCalculationService;
use App\Services\RoomQuoteSnapshotService;
use Carbon\Carbon;

/**
 * Extra guests (beyond room max_guests) pay a per-night extra guest fee that covers
 * breakfast, amenities and related services. It must be recorded as extra_guest_fee,
 * not meal_price, on both free-breakfast and buffet nights.
 */
beforeEach(function () {
    $this->room = Room::factory()->create(['slug' => 'garden-view', 'max_guests' => 6]);

    // 2 adults + 6 children in a room for 6 => 2 extra guests
    $this->bookingRoomArr = [(object) ['room_id' => 'garden-view', 'adults' => 2, 'children' => 6]];

    $this->calculate = function (array $nights, ?Promo $promo = null): array {
        $mealQuoteAction = Mockery::mock(ComputeMealQuoteAction::class);
        $mealQuoteAction->shouldReceive('execute')->andReturn(new MealQuoteDTO(nights: $nights, mealSubtotal: 0));

        $snapshotService = Mockery::mock(RoomQuoteSnapshotService::class);
        $snapshotService->shouldReceive('buildForStay')->andReturn(new RoomQuoteDTO(nights: [], totalRoom: 26000));

        $action = new CalculateBookingTotalAction(
            $mealQuoteAction,
            app(PromoCalculationService::class),
            Mockery::mock(RoomPricingServiceInterface::class),
            $snapshotService,
        );

        return $action->execute($this->bookingRoomArr, '2026-11-10', '2026-11-12', 2, 6, $promo);
    };

    $this->freeBreakfastNight = fn (string $date) => new MealNightDTO(
        date: Carbon::parse($date),
        type: 'free_breakfast',
        adultBreakfastPrice: 400,
        childBreakfastPrice: 400,
    );

    $this->buffetNight = fn (string $date) => new MealNightDTO(
        date: Carbon::parse($date),
        type: 'buffet',
        adultPrice: 1000,
        childPrice: 500,
        extraGuestFee: 300,
    );
});

it('records free-breakfast extra guest charges as extra guest fee, not meal price', function () {
    $totals = ($this->calculate)([
        ($this->freeBreakfastNight)('2026-11-10'),
        ($this->freeBreakfastNight)('2026-11-11'),
    ]);

    expect($totals['meal_total'])->toBe(0.0)
        ->and($totals['extra_guest_fee'])->toBe(1600.0)       // 2 extra guests x 400 x 2 nights
        ->and($totals['extra_guest_count'])->toBe(2)          // guests, not guest-nights
        ->and($totals['final_price'])->toBe(27600.0);         // grand total unchanged
});

it('splits a mixed stay into buffet meals and extra guest fees', function () {
    $totals = ($this->calculate)([
        ($this->freeBreakfastNight)('2026-11-10'),
        ($this->buffetNight)('2026-11-11'),
    ]);

    expect($totals['meal_total'])->toBe(5000.0)               // 2 x 1000 + 6 x 500 (all guests)
        ->and($totals['extra_guest_fee'])->toBe(1400.0)       // 2 x 400 (free breakfast) + 2 x 300 (buffet)
        ->and($totals['extra_guest_count'])->toBe(2)
        ->and($totals['final_price'])->toBe(32400.0);
});

it('charges no extra guest fee when guests are within room capacity', function () {
    $this->bookingRoomArr = [(object) ['room_id' => 'garden-view', 'adults' => 2, 'children' => 4]];

    $totals = ($this->calculate)([
        ($this->freeBreakfastNight)('2026-11-10'),
        ($this->buffetNight)('2026-11-11'),
    ]);

    expect($totals['extra_guest_fee'])->toBe(0.0)
        ->and($totals['extra_guest_count'])->toBe(0)
        ->and($totals['meal_total'])->toBe(4000.0);
});

it('does not apply meal-scoped promos to the free-breakfast extra guest fee', function () {
    $promo = Promo::factory()->create([
        'scope' => 'meal',
        'discount_type' => 'percentage',
        'discount_value' => 50,
        'per_night_calculation' => true,
        'starts_at' => null,
        'ends_at' => null,
        'expires_at' => null,
    ]);

    $totals = ($this->calculate)([
        ($this->freeBreakfastNight)('2026-11-10'),
        ($this->freeBreakfastNight)('2026-11-11'),
    ], $promo);

    expect($totals['promo_discount']['discount_amount'] ?? 0)->toEqual(0);
});
