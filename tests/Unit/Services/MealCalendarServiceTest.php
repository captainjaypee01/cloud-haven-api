<?php

use App\Contracts\Repositories\MealCalendarOverrideRepositoryInterface;
use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\Models\MealCalendarOverride;
use App\Models\MealProgram;
use App\Services\MealCalendarService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->programRepository = Mockery::mock(MealProgramRepositoryInterface::class);
    $this->overrideRepository = Mockery::mock(MealCalendarOverrideRepositoryInterface::class);
    $this->service = new MealCalendarService($this->programRepository, $this->overrideRepository);
});

it('returns false when no active program exists', function () {
    $this->programRepository->shouldReceive('getActive')
        ->once()
        ->andReturn(new Collection());

    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-10-03'));

    expect($result)->toBeFalse();
});

it('respects calendar overrides with highest precedence', function () {
    $program = new MealProgram([
        'id' => 1,
        'status' => 'active',
        'scope_type' => 'always',
    ]);
    $program->id = 1; // Ensure ID is set for the override lookup

    $override = new MealCalendarOverride([
        'is_active' => false,
    ]);

    $this->programRepository->shouldReceive('getActive')
        ->once()
        ->andReturn(new Collection([$program]));

    $this->overrideRepository->shouldReceive('getByProgramAndDate')
        ->once()
        ->with(1, Mockery::any())
        ->andReturn($override);

    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-10-03'));

    expect($result)->toBeFalse();
});

it('checks date range correctly', function () {
    $program = new MealProgram([
        'id' => 1,
        'status' => 'active',
        'scope_type' => 'date_range',
        'date_start' => Carbon::parse('2025-10-01'),
        'date_end' => Carbon::parse('2025-10-31'),
    ]);
    $program->id = 1; // Ensure ID is set for the override lookup

    $this->programRepository->shouldReceive('getActive')
        ->andReturn(new Collection([$program]));

    $this->overrideRepository->shouldReceive('getByProgramAndDate')
        ->andReturn(null);
    $this->overrideRepository->shouldReceive('getByProgramAndMonth')
        ->andReturn(null);

    // Within range
    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-10-15'));
    expect($result)->toBeTrue();

    // Outside range
    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-11-01'));
    expect($result)->toBeFalse();
});

it('checks monthly patterns correctly', function () {
    $program = new MealProgram([
        'id' => 1,
        'status' => 'active',
        'scope_type' => 'months',
        'months' => [3, 4, 5], // March, April, May
    ]);
    $program->id = 1; // Ensure ID is set for the override lookup

    $this->programRepository->shouldReceive('getActive')
        ->andReturn(new Collection([$program]));

    $this->overrideRepository->shouldReceive('getByProgramAndDate')
        ->andReturn(null);
    $this->overrideRepository->shouldReceive('getByProgramAndMonth')
        ->andReturn(null);

    // In specified months
    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-04-15'));
    expect($result)->toBeTrue();

    // Not in specified months
    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-06-15'));
    expect($result)->toBeFalse();
});

it('checks weekly patterns with FRI_SUN weekend definition', function () {
    $program = new MealProgram([
        'id' => 1,
        'status' => 'active',
        'scope_type' => 'weekly',
        'weekend_definition' => 'FRI_SUN',
        'weekdays' => null,
    ]);
    $program->id = 1; // Ensure ID is set for the override lookup

    $this->programRepository->shouldReceive('getActive')
        ->andReturn(new Collection([$program]));

    $this->overrideRepository->shouldReceive('getByProgramAndDate')
        ->andReturn(null);
    $this->overrideRepository->shouldReceive('getByProgramAndMonth')
        ->andReturn(null);

    // Friday (should be active)
    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-10-03')); // Friday
    expect($result)->toBeTrue();

    // Thursday (should be inactive)
    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-10-02')); // Thursday
    expect($result)->toBeFalse();
});

it('handles custom weekday patterns', function () {
    $program = new MealProgram([
        'id' => 1,
        'status' => 'active',
        'scope_type' => 'weekly',
        'weekend_definition' => 'CUSTOM',
        'weekdays' => ['MON', 'WED', 'FRI'],
    ]);
    $program->id = 1; // Ensure ID is set for the override lookup

    $this->programRepository->shouldReceive('getActive')
        ->andReturn(new Collection([$program]));

    $this->overrideRepository->shouldReceive('getByProgramAndDate')
        ->andReturn(null);
    $this->overrideRepository->shouldReceive('getByProgramAndMonth')
        ->andReturn(null);

    // Monday (should be active)
    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-10-06')); // Monday
    expect($result)->toBeTrue();

    // Tuesday (should be inactive)
    $result = $this->service->isBuffetActiveOn(Carbon::parse('2025-10-07')); // Tuesday
    expect($result)->toBeFalse();
});

it('logs warning when multiple active programs exist', function () {
    $program1 = new MealProgram(['id' => 1, 'status' => 'active', 'updated_at' => now()]);
    $program1->id = 1;
    $program2 = new MealProgram(['id' => 2, 'status' => 'active', 'updated_at' => now()->subDay()]);
    $program2->id = 2;
    $programs = new Collection([$program1, $program2]);

    $this->programRepository->shouldReceive('getActive')
        ->once()
        ->andReturn($programs);

    Log::shouldReceive('warning')
        ->once()
        ->with('Multiple active meal programs found. Using the most recently updated one.', Mockery::any());

    $this->overrideRepository->shouldReceive('getByProgramAndDate')
        ->andReturn(null);
    $this->overrideRepository->shouldReceive('getByProgramAndMonth')
        ->andReturn(null);

    // Call getAvailabilityForDateRange which uses getActiveProgram() and will trigger the warning
    $this->service->getAvailabilityForDateRange(Carbon::parse('2025-10-01'), Carbon::parse('2025-10-31'));
});
