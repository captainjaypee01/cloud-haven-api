<?php

namespace Database\Factories;

use App\Models\MealCalendarOverride;
use App\Models\MealProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealCalendarOverride>
 */
class MealCalendarOverrideFactory extends Factory
{
    protected $model = MealCalendarOverride::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $overrideType = fake()->randomElement(['date', 'month']);
        
        $data = [
            'meal_program_id' => MealProgram::factory(),
            'override_type' => $overrideType,
            'is_active' => fake()->boolean(),
            'note' => fake()->optional()->sentence(),
        ];
        
        if ($overrideType === 'date') {
            $data['date'] = fake()->dateTimeBetween('+1 week', '+6 months');
            $data['month'] = null;
            $data['year'] = null;
        } else {
            $data['date'] = null;
            $data['month'] = fake()->numberBetween(1, 12);
            $data['year'] = fake()->numberBetween(2025, 2026);
        }
        
        return $data;
    }

    /**
     * Indicate that the override forces buffet on.
     */
    public function forceOn(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'note' => 'Special event - buffet service added',
        ]);
    }

    /**
     * Indicate that the override forces buffet off.
     */
    public function forceOff(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'note' => 'Maintenance - no buffet service',
        ]);
    }

    /**
     * Create a date-specific override.
     */
    public function dateOverride(): static
    {
        return $this->state(fn (array $attributes) => [
            'override_type' => 'date',
            'date' => fake()->dateTimeBetween('+1 week', '+6 months'),
            'month' => null,
            'year' => null,
        ]);
    }

    /**
     * Create a month-wide override.
     */
    public function monthOverride(): static
    {
        return $this->state(fn (array $attributes) => [
            'override_type' => 'month',
            'date' => null,
            'month' => fake()->numberBetween(1, 12),
            'year' => fake()->numberBetween(2025, 2026),
        ]);
    }
}
