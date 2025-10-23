<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BookingRoom>
 */
class BookingRoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'room_id' => Room::factory(),
            'room_unit_id' => RoomUnit::factory(),
            'price_per_night' => fake()->numberBetween(1000, 5000),
            'adults' => fake()->numberBetween(1, 4),
            'children' => fake()->numberBetween(0, 2),
            'total_guests' => fake()->numberBetween(1, 6),
            'include_lunch' => fake()->boolean(),
            'include_pm_snack' => fake()->boolean(),
            'include_dinner' => fake()->boolean(),
            'lunch_cost' => fake()->numberBetween(200, 800),
            'pm_snack_cost' => fake()->numberBetween(100, 400),
            'dinner_cost' => fake()->numberBetween(300, 1000),
            'meal_cost' => fake()->numberBetween(500, 2000),
            'base_price' => fake()->numberBetween(2000, 8000),
            'total_price' => fake()->numberBetween(3000, 10000),
        ];
    }
}
