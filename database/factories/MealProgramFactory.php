<?php

namespace Database\Factories;

use App\Models\MealProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MealProgram>
 */
class MealProgramFactory extends Factory
{
    protected $model = MealProgram::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scopeType = fake()->randomElement(['always', 'date_range', 'months', 'weekly', 'composite']);
        
        $data = [
            'name' => fake()->words(3, true) . ' Buffet Program',
            'status' => fake()->randomElement(['active', 'inactive']),
            'scope_type' => $scopeType,
            'weekend_definition' => fake()->randomElement(['SAT_SUN', 'FRI_SUN', 'CUSTOM']),
            'inactive_label' => fake()->randomElement(['Free Breakfast', 'Complimentary Breakfast', 'Continental Breakfast']),
            'pm_snack_policy' => fake()->randomElement(['hidden', 'optional', 'required']),
            'buffet_enabled' => fake()->boolean(80), // 80% chance of being enabled
            'notes' => fake()->optional()->sentence(),
        ];

        // Add scope-specific fields
        switch ($scopeType) {
            case 'date_range':
                $startDate = fake()->dateTimeBetween('+1 month', '+6 months');
                $data['date_start'] = $startDate;
                $data['date_end'] = fake()->dateTimeBetween($startDate->format('Y-m-d') . ' +1 day', '+9 months');
                break;
                
            case 'months':
                $data['months'] = fake()->randomElements(range(1, 12), fake()->numberBetween(1, 4));
                break;
                
            case 'weekly':
                if ($data['weekend_definition'] === 'CUSTOM') {
                    $data['weekdays'] = fake()->randomElements(['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'], fake()->numberBetween(1, 4));
                }
                break;
                
            case 'composite':
                $startDate = fake()->dateTimeBetween('+1 month', '+6 months');
                $data['date_start'] = $startDate;
                $data['date_end'] = fake()->dateTimeBetween($startDate->format('Y-m-d') . ' +1 day', '+9 months');
                $data['months'] = fake()->randomElements(range(1, 12), fake()->numberBetween(1, 3));
                break;
        }

        return $data;
    }

    /**
     * Indicate that the program is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the program is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
