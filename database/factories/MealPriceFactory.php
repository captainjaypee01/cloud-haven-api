<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealPrice>
 */
class MealPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => $this->faker->randomElement(['adult', 'child', 'infant']),
            'min_age' => 0,
            'max_age' => 99,
            'price' => $this->faker->randomElement([1700, 1000, 0]),
        ];
    }
}
