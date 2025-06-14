<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Amenity::factory()->create(['name'  => 'Pool Access']);
        Amenity::factory()->create(['name'  => 'Free WiFi']);
        Amenity::factory()->create(['name'  => 'Free Breakfast']);
        Amenity::factory()->create(['name'  => 'Mountain View']);
        Amenity::factory()->create(['name'  => 'Room Service']);
    }
}
