<?php

use App\Contracts\Repositories\MealPricingTierRepositoryInterface;
use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\Models\MealPricingTier;
use App\Models\MealProgram;
use App\Services\MealPricingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->calendarService = Mockery::mock(MealCalendarServiceInterface::class);
    $this->programRepository = Mockery::mock(MealProgramRepositoryInterface::class);
    $this->tierRepository = Mockery::mock(MealPricingTierRepositoryInterface::class);
    
    $this->service = new MealPricingService(
        $this->calendarService,
        $this->programRepository,
        $this->tierRepository
    );
});

it('calculates correct meal quote for buffet nights', function () {
    $program = new MealProgram([
        'id' => 1,
        'status' => 'active',
        'inactive_label' => 'Free Breakfast',
    ]);

    $tier = new MealPricingTier([
        'adult_price' => 300.00,
        'child_price' => 150.00,
    ]);

    $this->programRepository->shouldReceive('getActive')
        ->andReturn(collect([$program]));

    $this->calendarService->shouldReceive('isBuffetActiveOn')
        ->times(3)
        ->andReturn(true);

    $this->tierRepository->shouldReceive('getEffectiveTierForDate')
        ->times(3)
        ->andReturn($tier);

    $quote = $this->service->quoteForStay(
        Carbon::parse('2025-10-03'),
        Carbon::parse('2025-10-06'),
        2, // adults
        1  // children
    );

    expect($quote->nights)->toHaveCount(3);
    expect($quote->mealSubtotal)->toBe(2250.00); // 3 nights × (2×300 + 1×150)
    expect($quote->buffetNightsCount())->toBe(3);
    expect($quote->freeBreakfastNightsCount())->toBe(0);
});

it('handles mixed buffet and free breakfast nights', function () {
    $program = new MealProgram([
        'id' => 1,
        'status' => 'active',
        'inactive_label' => 'Free Breakfast',
    ]);

    $tier = new MealPricingTier([
        'adult_price' => 300.00,
        'child_price' => 150.00,
    ]);

    $this->programRepository->shouldReceive('getActive')
        ->andReturn(collect([$program]));

    // First night: buffet, Second night: buffet, Third night: free
    $this->calendarService->shouldReceive('isBuffetActiveOn')
        ->times(3)
        ->andReturn(true, true, false);

    $this->tierRepository->shouldReceive('getEffectiveTierForDate')
        ->times(2)
        ->andReturn($tier);

    $quote = $this->service->quoteForStay(
        Carbon::parse('2025-10-03'),
        Carbon::parse('2025-10-06'),
        2, // adults
        1  // children
    );

    expect($quote->nights)->toHaveCount(3);
    expect($quote->mealSubtotal)->toBe(1500.00); // 2 nights × (2×300 + 1×150)
    expect($quote->buffetNightsCount())->toBe(2);
    expect($quote->freeBreakfastNightsCount())->toBe(1);
});

it('returns all free breakfast when no program is active', function () {
    $this->programRepository->shouldReceive('getActive')
        ->andReturn(new Collection());

    $this->calendarService->shouldReceive('isBuffetActiveOn')
        ->times(3)
        ->andReturn(false);

    $quote = $this->service->quoteForStay(
        Carbon::parse('2025-10-03'),
        Carbon::parse('2025-10-06'),
        2, // adults
        1  // children
    );

    expect($quote->nights)->toHaveCount(3);
    expect($quote->mealSubtotal)->toBe(0.00);
    expect($quote->buffetNightsCount())->toBe(0);
    expect($quote->freeBreakfastNightsCount())->toBe(3);
});

it('handles missing pricing tier gracefully', function () {
    $program = new MealProgram([
        'id' => 1,
        'status' => 'active',
        'inactive_label' => 'Free Breakfast',
    ]);

    $this->programRepository->shouldReceive('getActive')
        ->andReturn(collect([$program]));

    $this->calendarService->shouldReceive('isBuffetActiveOn')
        ->once()
        ->andReturn(true);

    $this->tierRepository->shouldReceive('getEffectiveTierForDate')
        ->once()
        ->andReturn(null); // No pricing tier found

    $quote = $this->service->quoteForStay(
        Carbon::parse('2025-10-03'),
        Carbon::parse('2025-10-04'),
        2, // adults
        1  // children
    );

    expect($quote->nights)->toHaveCount(1);
    expect($quote->mealSubtotal)->toBe(0.00);
    expect($quote->nights[0]->type)->toBe('free_breakfast');
});

it('respects property timezone for date calculations', function () {
    config(['resort.timezone' => 'Asia/Singapore']);

    $program = new MealProgram([
        'id' => 1,
        'status' => 'active',
        'inactive_label' => 'Free Breakfast',
    ]);

    $this->programRepository->shouldReceive('getActive')
        ->andReturn(collect([$program]));

    $this->calendarService->shouldReceive('isBuffetActiveOn')
        ->with(Mockery::on(function ($date) {
            return $date->timezone->getName() === 'Asia/Singapore';
        }))
        ->once()
        ->andReturn(false);

    $quote = $this->service->quoteForStay(
        Carbon::parse('2025-10-03 15:00:00', 'UTC'), // UTC time
        Carbon::parse('2025-10-04 15:00:00', 'UTC'),
        1,
        0
    );

    expect($quote->nights)->toHaveCount(1);
});
