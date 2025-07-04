<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();
        return [
            // 'id' => not set (auto-increment integer)
            'user_id' => null, // or User::factory()
            'reference_number' => 'NTDL-' . $now->format('ymd') . '-' . strtoupper(Str::random(6)),
            'check_in_date' => $now->copy()->addDays(5)->format('Y-m-d'),
            'check_in_time' => '14:00',
            'check_out_date' => $now->copy()->addDays(7)->format('Y-m-d'),
            'check_out_time' => '12:00',
            'guest_name' => $this->faker->name(),
            'guest_email' => $this->faker->safeEmail(),
            'guest_phone' => $this->faker->phoneNumber(),
            'special_requests' => $this->faker->optional()->sentence(),
            'adults' => $this->faker->numberBetween(1, 5),
            'children' => $this->faker->numberBetween(0, 3),
            'total_guests' => function (array $attrs) {
                return ($attrs['adults'] ?? 0) + ($attrs['children'] ?? 0);
            },
            'promo_id' => null,
            'total_price' => 1000,
            'discount_amount' => 0,
            'final_price' => 1000,
            'status' => $this->faker->randomElement(['pending', 'downpayment', 'paid']),
            'reserved_until' => $now->copy()->addMinutes(15),
            'downpayment_at' => null,
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
