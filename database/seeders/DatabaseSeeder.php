<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Production-safe seeders (always run)
        $this->call([
            RoomSeeder::class,
            RoomUnitSeeder::class, // Create room units based on room quantities
            AmenitySeeder::class,
            PromoSeeder::class,
            MealPriceSeeder::class,
        ]);

        // Development/staging seeders (only run in non-production environments)
        if (app()->environment(['local', 'dev', 'development', 'staging', 'uat'])) {
            $this->call([
                // BookingSeeder::class, // Commented out in favor of new seeder
                BookingWithRoomUnitsSeeder::class,
                ReviewSeeder::class,
            ]);
        } else {
            $this->command->info('Skipping BookingSeeder and ReviewSeeder in production environment');
        }
    }
}
