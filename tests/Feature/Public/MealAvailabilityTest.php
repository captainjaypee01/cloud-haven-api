<?php

use App\Models\MealCalendarOverride;
use App\Models\MealPricingTier;
use App\Models\MealProgram;
use Carbon\Carbon;

it('can get meal availability for date range', function () {
    // Create an active program for weekends
    $program = MealProgram::factory()->create([
        'status' => 'active',
        'scope_type' => 'weekly',
        'weekend_definition' => 'SAT_SUN',
    ]);

    $response = $this->getJson('/api/v1/public/meal-availability?' . http_build_query([
        'from' => '2025-10-03',
        'to' => '2025-10-05',
    ]));

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                '2025-10-03', // Friday - should be free_breakfast
                '2025-10-04', // Saturday - should be buffet
                '2025-10-05', // Sunday - should be buffet
            ],
        ]);
});

it('validates date range for availability', function () {
    $response = $this->getJson('/api/v1/public/meal-availability?' . http_build_query([
        'from' => '2025-10-05',
        'to' => '2025-10-03', // End before start
    ]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

it('limits date range to 365 days', function () {
    $response = $this->getJson('/api/v1/public/meal-availability?' . http_build_query([
        'from' => '2025-01-01',
        'to' => '2026-01-02', // 366 days
    ]));

    $response->assertUnprocessable()
        ->assertJsonFragment([
            'message' => 'Date range cannot exceed 365 days',
        ]);
});

it('can get meal quote for stay', function () {
    // Create an active program
    $program = MealProgram::factory()->create([
        'status' => 'active',
        'scope_type' => 'always',
    ]);

    // Create pricing tier
    MealPricingTier::factory()->create([
        'meal_program_id' => $program->id,
        'adult_price' => 300.00,
        'child_price' => 150.00,
        'currency' => 'SGD',
    ]);

    $response = $this->postJson('/api/v1/public/quotes/meal', [
        'check_in' => '2025-10-03',
        'check_out' => '2025-10-06',
        'adults' => 2,
        'children' => 1,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'nights' => [
                    '*' => [
                        'date',
                        'type',
                        'adults',
                        'children',
                        'night_total',
                    ],
                ],
                'meal_subtotal',
                'labels',
            ],
        ]);

    // Verify calculation
    $data = $response->json('data');
    expect($data['nights'])->toHaveCount(3); // 3 nights
    expect($data['meal_subtotal'])->toBe(2250.0); // 3 nights × (2×300 + 1×150)
});

it('handles free breakfast when no program is active', function () {
    // No active programs

    $response = $this->postJson('/api/v1/public/quotes/meal', [
        'check_in' => '2025-10-03',
        'check_out' => '2025-10-04',
        'adults' => 2,
        'children' => 1,
    ]);

    $response->assertOk();

    $data = $response->json('data');
    expect($data['nights'][0]['type'])->toBe('free_breakfast');
    expect($data['nights'][0]['night_total'])->toBe(0.0);
    expect($data['meal_subtotal'])->toBe(0.0);
});

it('respects calendar overrides in quotes', function () {
    $program = MealProgram::factory()->create([
        'status' => 'active',
        'scope_type' => 'always',
    ]);

    MealPricingTier::factory()->create([
        'meal_program_id' => $program->id,
        'adult_price' => 300.00,
        'child_price' => 150.00,
    ]);

    // Create override to force buffet OFF on Oct 4
    MealCalendarOverride::factory()->create([
        'meal_program_id' => $program->id,
        'date' => Carbon::parse('2025-10-04'),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/v1/public/quotes/meal', [
        'check_in' => '2025-10-03',
        'check_out' => '2025-10-05',
        'adults' => 2,
        'children' => 0,
    ]);

    $response->assertOk();

    $nights = $response->json('data.nights');
    expect($nights[0]['type'])->toBe('buffet'); // Oct 3 - buffet
    expect($nights[0]['night_total'])->toBe(600.0);
    expect($nights[1]['type'])->toBe('free_breakfast'); // Oct 4 - overridden to free
    expect($nights[1]['night_total'])->toBe(0.0);
});

it('validates meal quote request', function () {
    $response = $this->postJson('/api/v1/public/quotes/meal', [
        'check_in' => 'invalid-date',
        'check_out' => '2025-10-06',
        'adults' => 0,
        'children' => -1,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['check_in', 'adults', 'children']);
});

it('prevents past check-in dates', function () {
    $response = $this->postJson('/api/v1/public/quotes/meal', [
        'check_in' => '2020-01-01',
        'check_out' => '2020-01-02',
        'adults' => 1,
        'children' => 0,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['check_in']);
});
