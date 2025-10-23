<?php

namespace Database\Factories;

use App\Enums\RoomUnitStatusEnum;
use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomUnit>
 */
class RoomUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'unit_number' => fake()->numerify('###'),
            'status' => RoomUnitStatusEnum::AVAILABLE,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the unit is available.
     */
    public function available()
    {
        return $this->state([
            'status' => RoomUnitStatusEnum::AVAILABLE,
        ]);
    }

    /**
     * Indicate that the unit is in maintenance.
     */
    public function maintenance()
    {
        return $this->state([
            'status' => RoomUnitStatusEnum::MAINTENANCE,
            'maintenance_start_at' => fake()->dateTimeBetween('-1 week', '+1 week'),
            'maintenance_end_at' => fake()->dateTimeBetween('+1 week', '+2 weeks'),
        ]);
    }

    /**
     * Indicate that the unit is blocked.
     */
    public function blocked()
    {
        return $this->state([
            'status' => RoomUnitStatusEnum::BLOCKED,
            'blocked_start_at' => fake()->dateTimeBetween('-1 week', '+1 week'),
            'blocked_end_at' => fake()->dateTimeBetween('+1 week', '+2 weeks'),
        ]);
    }
}
