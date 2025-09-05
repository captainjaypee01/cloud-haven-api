<?php

namespace Database\Factories;

use App\Models\MealPricingTier;
use App\Models\MealProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealPricingTier>
 */
class MealPricingTierFactory extends Factory
{
    protected $model = MealPricingTier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $effectiveFrom = fake()->optional(0.7)->dateTimeBetween('-1 year', '+1 year');
        
        return [
            'meal_program_id' => MealProgram::factory(),
            'currency' => fake()->randomElement(['SGD', 'USD', 'EUR', 'PHP']),
            'adult_price' => fake()->randomFloat(2, 100, 500),
            'child_price' => fake()->randomFloat(2, 50, 250),
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveFrom ? fake()->optional(0.5)->dateTimeBetween($effectiveFrom, '+1 year') : null,
        ];
    }
}
