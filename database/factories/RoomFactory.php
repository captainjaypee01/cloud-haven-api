<?php

namespace Database\Factories;

use App\Models\Amenity;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'short_description' => fake()->paragraph(1),
            'description' => fake()->text(),
            'max_guests' => fake()->numberBetween(1, 8),
            'extra_guest_fee' => 1000,
            'quantity' => fake()->randomNumber(1),
            'allows_day_use' => fake()->boolean(),
            'base_weekday_rate' => fake()->numberBetween(10000, 16000),
            'base_weekend_rate' => fake()->numberBetween(10000, 16000),
        ];
    }
    public function configure()
    {
        $amenities = Amenity::all()->pluck('id');
        return $this->afterCreating(function (Room $room) use ($amenities){
            $room->amenities()->sync($amenities);
        });
    }

    /**
     * Indicate that the model's status should be available.
     */
    public function available()
    {
        return $this->state([
            'status' => 1
        ]);
    }

    /**
     * Indicate that the model's status should be available.
     */
    public function unavailable()
    {
        return $this->state([
            'status' => 0
        ]);
    }

    /**
     * Indicate that the model's status should be available.
     */
    public function archived()
    {
        return $this->state([
            'status' => 2
        ]);
    }
}
