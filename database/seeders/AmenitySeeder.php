<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Room;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define all amenities with their icons (using correct lucide-react PascalCase names)
        $amenities = [
            // Common amenities (attach broadly)
            ['name' => 'Free Parking', 'icon' => 'Car', 'description' => 'Complimentary parking available for all guests'],
            ['name' => 'Pool Access', 'icon' => 'Waves', 'description' => 'Access to resort swimming pool'],
            ['name' => 'Air Condition', 'icon' => 'Snowflake', 'description' => 'Climate controlled room environment'],
            ['name' => 'Television', 'icon' => 'Tv', 'description' => 'Flat screen TV with cable channels'],
            ['name' => 'Mini-Refrigerator', 'icon' => 'Refrigerator', 'description' => 'In-room mini fridge for beverages and snacks'],
            ['name' => 'Complimentary Coffee', 'icon' => 'Coffee', 'description' => 'Free coffee and tea making facilities'],
            ['name' => 'Complimentary Bottle Water', 'icon' => 'Droplets', 'description' => 'Complimentary drinking water provided'],
            ['name' => 'Hot & Cold Shower', 'icon' => 'ShowerHead', 'description' => 'Modern bathroom with hot and cold water'],
            ['name' => 'Toiletries/Towel', 'icon' => 'Bath', 'description' => 'Premium toiletries and fresh towels provided'],
            ['name' => 'Comfort Room', 'icon' => 'DoorOpen', 'description' => 'Private bathroom facilities'],
            
            // Room-specific amenities
            ['name' => '2 Queen Size bed', 'icon' => 'BedDouble', 'description' => 'Two comfortable queen size beds'],
            ['name' => '2 Extra Mattress', 'icon' => 'Bed', 'description' => 'Two additional mattresses for extra guests'],
            ['name' => '1 Queen Size bed', 'icon' => 'BedSingle', 'description' => 'One comfortable queen size bed'],
            ['name' => '1 Extra Mattress', 'icon' => 'Bed', 'description' => 'One additional mattress for extra guests'],
            ['name' => 'Private Veranda', 'icon' => 'Home', 'description' => 'Private outdoor veranda space'],
            ['name' => 'Open Veranda', 'icon' => 'Wind', 'description' => 'Open veranda with scenic views'],
            
            // Extra amenities (create but don't attach)
            ['name' => 'Safety Deposit Box', 'icon' => 'Shield', 'description' => 'Secure storage for valuables'],
            ['name' => 'Work Desk', 'icon' => 'Monitor', 'description' => 'Dedicated workspace area'],
            ['name' => 'Hair Dryer', 'icon' => 'Wind', 'description' => 'Hair dryer available in bathroom'],
            ['name' => 'WiFi', 'icon' => 'Wifi', 'description' => 'High-speed wireless internet access'],
        ];

        // Create all amenities
        foreach ($amenities as $amenityData) {
            Amenity::firstOrCreate(
                ['name' => $amenityData['name']],
                $amenityData
            );
        }

        // Get rooms by slug for specific attachments
        $poolViewGround = Room::where('slug', 'pool-view-ground-floor')->first();
        $poolViewSecond = Room::where('slug', 'pool-view-second-floor')->first();
        $gardenViewGround = Room::where('slug', 'garden-view-ground-floor')->first();
        $gardenViewSecond = Room::where('slug', 'garden-view-second-floor')->first();

        if (!$poolViewGround || !$poolViewSecond || !$gardenViewGround || !$gardenViewSecond) {
            $this->command->warn('Some rooms not found. Make sure to run RoomSeeder first.');
            return;
        }

        // Define room-amenity mappings
        $roomAmenityMappings = [
            $poolViewGround->id => [
                'Free Parking', 'Pool Access', '2 Queen Size bed', '2 Extra Mattress',
                'Air Condition', 'Television', 'Mini-Refrigerator', 'Complimentary Coffee',
                'Complimentary Bottle Water', 'Hot & Cold Shower', 'Toiletries/Towel', 'Comfort Room'
            ],
            $poolViewSecond->id => [
                'Free Parking', 'Pool Access', '1 Queen Size bed', '1 Extra Mattress',
                'Air Condition', 'Television', 'Mini-Refrigerator', 'Complimentary Coffee',
                'Complimentary Bottle Water', 'Hot & Cold Shower', 'Toiletries/Towel', 'Comfort Room',
                'Open Veranda'
            ],
            $gardenViewGround->id => [
                'Free Parking', 'Pool Access', '2 Queen Size bed', '2 Extra Mattress',
                'Air Condition', 'Television', 'Mini-Refrigerator', 'Complimentary Coffee',
                'Complimentary Bottle Water', 'Hot & Cold Shower', 'Toiletries/Towel', 'Comfort Room',
                'Private Veranda'
            ],
            $gardenViewSecond->id => [
                'Free Parking', 'Pool Access', '2 Queen Size bed', '2 Extra Mattress',
                'Air Condition', 'Television', 'Mini-Refrigerator', 'Complimentary Coffee',
                'Complimentary Bottle Water', 'Hot & Cold Shower', 'Toiletries/Towel', 'Comfort Room',
                'Open Veranda'
            ],
        ];

        // Attach amenities to rooms
        foreach ($roomAmenityMappings as $roomId => $amenityNames) {
            $room = Room::find($roomId);
            $amenityIds = Amenity::whereIn('name', $amenityNames)->pluck('id')->toArray();
            
            // Use syncWithoutDetaching to avoid duplicates
            $room->amenities()->syncWithoutDetaching($amenityIds);
        }

        $this->command->info('Successfully seeded amenities and attached them to rooms.');
        $this->command->info('Extra amenities (Safety Deposit Box, Work Desk, Hair Dryer, WiFi) created but not attached.');
    }
}
