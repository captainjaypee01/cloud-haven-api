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
            AmenitySeeder::class,
            PromoSeeder::class,
            MealPriceSeeder::class,
        ]);

        // Development/staging seeders (only run in non-production environments)
        if (app()->environment(['local', 'dev', 'development', 'staging', 'uat'])) {
            $this->call([
                BookingSeeder::class,
                ReviewSeeder::class,
            ]);
        } else {
            $this->command->info('Skipping BookingSeeder and ReviewSeeder in production environment');
        }
    }
}
