<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PromoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    { 
        // Create 15 random promo codes
        Promo::factory()->count(15)->create();

        // Optionally, create some specific promos for testing
        Promo::factory()->create([
            'code' => 'WELCOME10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'expires_at' => now()->addMonths(3),
            'max_uses' => 50,
            'uses_count' => 0,
            'active' => true,
        ]);

        Promo::factory()->create([
            'code' => 'SUMMER2025',
            'discount_type' => 'fixed',
            'discount_value' => 500,
            'expires_at' => now()->addMonths(1),
            'max_uses' => null,
            'uses_count' => 0,
            'active' => false,
        ]);
    }
}
