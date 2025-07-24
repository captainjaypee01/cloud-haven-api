<?php

namespace Database\Factories;

use App\Models\Promo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Promo>
 */
class PromoFactory extends Factory
{
    protected $model = Promo::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $discountType = $this->faker->randomElement(['fixed', 'percentage']);
        $discountValue = $discountType === 'fixed'
            ? $this->faker->randomFloat(2, 100, 2000) // e.g., 100 - 2000 for fixed
            : $this->faker->numberBetween(5, 40); // 5% - 40% for percentage

        return [
            'code'           => strtoupper(Str::random(8)),
            'discount_type'  => $discountType,
            'discount_value' => $discountValue,
            'expires_at'     => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'max_uses'       => $this->faker->optional()->numberBetween(10, 100),
            'uses_count'     => 0,
            'active'         => $this->faker->boolean(75), // 75% chance to be active
        ];
    }
}
