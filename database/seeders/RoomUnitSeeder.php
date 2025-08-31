<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomUnit;
use App\Enums\RoomUnitStatusEnum;
use Illuminate\Database\Seeder;

class RoomUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting RoomUnitSeeder...');

        // Get all rooms
        $rooms = Room::where('status', 1)->get();

        foreach ($rooms as $room) {
            $this->createRoomUnitsForRoom($room);
        }

        $this->command->info('RoomUnitSeeder completed successfully!');
    }

    private function createRoomUnitsForRoom(Room $room): void
    {
        $quantity = $room->quantity;
        $roomName = $room->name;
        
        $this->command->info("Creating {$quantity} units for: {$roomName}");

        // Determine unit number range based on room type
        $unitNumbers = $this->getUnitNumbersForRoom($roomName, $quantity);

        foreach ($unitNumbers as $unitNumber) {
            RoomUnit::updateOrCreate(
                [
                    'room_id' => $room->id,
                    'unit_number' => $unitNumber,
                ],
                [
                    'room_id' => $room->id,
                    'unit_number' => $unitNumber,
                    'status' => RoomUnitStatusEnum::AVAILABLE,
                    'notes' => "Unit {$unitNumber} for {$roomName}",
                ]
            );
        }

        $this->command->info("✅ Created {$quantity} units for {$roomName}");
    }

    private function getUnitNumbersForRoom(string $roomName, int $quantity): array
    {
        // Define unit number ranges based on room type
        if (str_contains($roomName, 'Pool View - Ground Floor')) {
            return range(101, 101 + $quantity - 1); // 101-102
        }
        
        if (str_contains($roomName, 'Pool View - Second Floor')) {
            return range(201, 201 + $quantity - 1); // 201-202
        }
        
        if (str_contains($roomName, 'Garden View - Ground Floor')) {
            return range(301, 301 + $quantity - 1); // 301-306
        }
        
        if (str_contains($roomName, 'Garden View - Second Floor')) {
            return range(401, 401 + $quantity - 1); // 401-406
        }

        // Fallback: generate sequential numbers
        return range(1, $quantity);
    }
}
