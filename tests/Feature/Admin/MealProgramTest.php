<?php

use App\Models\MealProgram;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin);
});

it('can list meal programs', function () {
    MealProgram::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/admin/meal-programs');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'status',
                    'scope_type',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);
});

it('can create a meal program with date range scope', function () {
    $data = [
        'name' => 'October Special',
        'status' => 'active',
        'scope_type' => 'date_range',
        'date_start' => '2025-10-01',
        'date_end' => '2025-10-31',
        'inactive_label' => 'Free Breakfast',
        'pm_snack_policy' => 'optional',
        'buffet_enabled' => true,
    ];

    $response = $this->postJson('/api/v1/admin/meal-programs', $data);

    $response->assertCreated()
        ->assertJsonFragment([
            'success' => true,
            'name' => 'October Special',
        ]);

    $this->assertDatabaseHas('meal_programs', [
        'name' => 'October Special',
        'scope_type' => 'date_range',
    ]);
});

it('validates required fields when creating meal program', function () {
    $response = $this->postJson('/api/v1/admin/meal-programs', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'status', 'scope_type']);
});

it('requires date range for date_range scope type', function () {
    $data = [
        'name' => 'Invalid Program',
        'status' => 'active',
        'scope_type' => 'date_range',
        // Missing date_start and date_end
    ];

    $response = $this->postJson('/api/v1/admin/meal-programs', $data);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['date_start', 'date_end']);
});

it('can update a meal program', function () {
    $program = MealProgram::factory()->create([
        'name' => 'Old Name',
        'status' => 'inactive',
    ]);

    $data = [
        'name' => 'Updated Name',
        'status' => 'active',
        'scope_type' => 'always',
        'pm_snack_policy' => 'optional',
        'buffet_enabled' => true,
    ];

    $response = $this->putJson("/api/v1/admin/meal-programs/{$program->id}", $data);

    $response->assertOk()
        ->assertJsonFragment([
            'success' => true,
            'name' => 'Updated Name',
            'status' => 'active',
        ]);

    $this->assertDatabaseHas('meal_programs', [
        'id' => $program->id,
        'name' => 'Updated Name',
        'status' => 'active',
    ]);
});

it('can delete a meal program', function () {
    $program = MealProgram::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/meal-programs/{$program->id}");

    $response->assertOk()
        ->assertJsonFragment([
            'success' => true,
            'message' => 'Meal program deleted successfully',
        ]);

    $this->assertSoftDeleted('meal_programs', [
        'id' => $program->id,
    ]);
});

it('can preview meal program calendar', function () {
    $program = MealProgram::factory()->create([
        'status' => 'active',
        'scope_type' => 'always',
    ]);

    $response = $this->getJson("/api/v1/admin/meal-programs/{$program->id}/preview?" . http_build_query([
        'from' => '2025-10-01',
        'to' => '2025-10-05',
    ]));

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                '2025-10-01',
                '2025-10-02',
                '2025-10-03',
                '2025-10-04',
                '2025-10-05',
            ],
        ]);
});

it('validates months array for months scope type', function () {
    $data = [
        'name' => 'Summer Program',
        'status' => 'active',
        'scope_type' => 'months',
        'months' => [0, 13], // Invalid month values
    ];

    $response = $this->postJson('/api/v1/admin/meal-programs', $data);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['months.0', 'months.1']);
});

it('validates weekdays for weekly scope type', function () {
    $data = [
        'name' => 'Weekend Program',
        'status' => 'active',
        'scope_type' => 'weekly',
        'weekdays' => ['INVALID', 'DAY'],
        'weekend_definition' => 'CUSTOM',
    ];

    $response = $this->postJson('/api/v1/admin/meal-programs', $data);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['weekdays.0', 'weekdays.1']);
});

it('requires authentication', function () {
    auth()->logout();

    $response = $this->getJson('/api/v1/admin/meal-programs');

    $response->assertUnauthorized();
});

it('requires admin role', function () {
    $user = User::factory()->create(['role' => 'user']);
    $this->actingAs($user);

    $response = $this->getJson('/api/v1/admin/meal-programs');

    $response->assertForbidden();
});

it('can create a meal program with buffet disabled', function () {
    $data = [
        'name' => 'PM Snack Only Program',
        'status' => 'active',
        'scope_type' => 'months',
        'months' => [9], // September
        'inactive_label' => 'Free Breakfast',
        'pm_snack_policy' => 'optional',
        'buffet_enabled' => false,
    ];

    $response = $this->postJson('/api/v1/admin/meal-programs', $data);

    $response->assertCreated()
        ->assertJsonFragment([
            'name' => 'PM Snack Only Program',
            'buffet_enabled' => false,
        ]);

    $this->assertDatabaseHas('meal_programs', [
        'name' => 'PM Snack Only Program',
        'buffet_enabled' => false,
    ]);
});
