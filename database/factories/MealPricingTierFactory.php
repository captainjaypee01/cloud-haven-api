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
            'adult_lunch_price' => fake()->optional(0.6)->randomFloat(2, 30, 150),
            'child_lunch_price' => fake()->optional(0.6)->randomFloat(2, 15, 75),
            'adult_pm_snack_price' => fake()->optional(0.4)->randomFloat(2, 10, 50),
            'child_pm_snack_price' => fake()->optional(0.4)->randomFloat(2, 5, 25),
            'adult_dinner_price' => fake()->optional(0.3)->randomFloat(2, 50, 200),
            'child_dinner_price' => fake()->optional(0.3)->randomFloat(2, 25, 100),
            'adult_breakfast_price' => fake()->optional(0.5)->randomFloat(2, 15, 80),
            'child_breakfast_price' => fake()->optional(0.5)->randomFloat(2, 8, 40),
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveFrom ? fake()->optional(0.5)->dateTimeBetween($effectiveFrom, '+1 year') : null,
        ];
    }
}
