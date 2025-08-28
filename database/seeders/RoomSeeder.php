<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = [
            [
                'name' => 'Pool View - Ground Floor',
                'slug' => Str::slug('Pool View - Ground Floor'),
                'short_description' => 'Spacious ground floor room with direct pool access and stunning views.',
                'description' => 'Experience luxury and convenience in our ground floor pool view room. Features direct access to the swimming pool area, perfect for families and groups. The room offers panoramic views of our resort pool and garden areas, making it an ideal choice for those who want to be close to all the action.',
                'max_guests' => 6,
                'extra_guests' => 2,
                'extra_guest_fee' => 0,
                'quantity' => 2,
                'allows_day_use' => false,
                'base_weekday_rate' => 15000.00,
                'base_weekend_rate' => 15000.00,
                'price_per_night' => 15000.00,
                'is_featured' => 1,
                'status' => 1,
            ],
            [
                'name' => 'Pool View - Second Floor',
                'slug' => Str::slug('Pool View - Second Floor'),
                'short_description' => 'Intimate second floor room with elevated pool views.',
                'description' => 'Enjoy elevated views of our resort pool from the comfort of your second floor accommodation. This cozy room is perfect for couples seeking a peaceful retreat with beautiful pool vistas. Features modern amenities and a private atmosphere while maintaining easy access to all resort facilities.',
                'max_guests' => 2,
                'extra_guests' => 2,
                'extra_guest_fee' => 0,
                'quantity' => 2,
                'allows_day_use' => false,
                'base_weekday_rate' => 10100.00,
                'base_weekend_rate' => 10100.00,
                'price_per_night' => 10100.00,
                'is_featured' => 0,
                'status' => 1,
            ],
            [
                'name' => 'Garden View - Ground Floor',
                'slug' => Str::slug('Garden View - Ground Floor'),
                'short_description' => 'Serene ground floor room overlooking lush tropical gardens.',
                'description' => 'Immerse yourself in nature with our garden view ground floor accommodations. Surrounded by lush tropical landscaping, these rooms offer a tranquil escape with easy ground-level access. Perfect for families and groups who appreciate the beauty of our resort gardens and prefer convenient access to outdoor areas.',
                'max_guests' => 6,
                'extra_guests' => 2,
                'extra_guest_fee' => 0,
                'quantity' => 6,
                'allows_day_use' => false,
                'base_weekday_rate' => 13000.00,
                'base_weekend_rate' => 13000.00,
                'price_per_night' => 13000.00,
                'is_featured' => 1,
                'status' => 1,
            ],
            [
                'name' => 'Garden View - Second Floor',
                'slug' => Str::slug('Garden View - Second Floor'),
                'short_description' => 'Elevated garden views with peaceful second floor setting.',
                'description' => 'Experience tranquility from an elevated perspective in our second floor garden view rooms. These accommodations offer sweeping views of our beautifully maintained tropical gardens and surrounding landscape. Ideal for guests seeking peace and quiet while enjoying the natural beauty of our resort environment.',
                'max_guests' => 6,
                'extra_guests' => 2,
                'extra_guest_fee' => 0,
                'quantity' => 6,
                'allows_day_use' => false,
                'base_weekday_rate' => 13000.00,
                'base_weekend_rate' => 13000.00,
                'price_per_night' => 13000.00,
                'is_featured' => 0,
                'status' => 1,
            ],
        ];

        foreach ($rooms as $roomData) {
            Room::updateOrCreate(
                ['slug' => $roomData['slug']],
                $roomData
            );
        }

        $this->command->info('Successfully seeded 4 room types with proper quantities.');
    }
}
