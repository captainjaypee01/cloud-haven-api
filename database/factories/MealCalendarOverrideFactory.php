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
        return [
            'meal_program_id' => MealProgram::factory(),
            'date' => fake()->dateTimeBetween('+1 week', '+6 months'),
            'is_active' => fake()->boolean(),
            'note' => fake()->optional()->sentence(),
        ];
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
}
