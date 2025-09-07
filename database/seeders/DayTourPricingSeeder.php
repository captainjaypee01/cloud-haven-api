<?php

namespace Database\Seeders;

use App\Models\DayTourPricing;
use Illuminate\Database\Seeder;

class DayTourPricingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pricingData = [
            [
                'name' => 'September 2024 Pricing',
                'description' => 'Includes entrance fee, parking, pool access, beach access, WiFi, and plated lunch',
                'price_per_pax' => 800.00,
                'effective_from' => '2024-09-01',
                'effective_until' => '2024-09-30',
                'is_active' => true,
            ],
            [
                'name' => 'October 2024 Pricing',
                'description' => 'Includes entrance fee, parking, pool access, beach access, WiFi, and plated lunch',
                'price_per_pax' => 850.00,
                'effective_from' => '2024-10-01',
                'effective_until' => '2024-10-31',
                'is_active' => true,
            ],
            [
                'name' => 'November 2024 Pricing',
                'description' => 'Includes entrance fee, parking, pool access, beach access, WiFi, and plated lunch',
                'price_per_pax' => 900.00,
                'effective_from' => '2024-11-01',
                'effective_until' => '2024-11-30',
                'is_active' => true,
            ],
            [
                'name' => 'December 2024 Pricing',
                'description' => 'Includes entrance fee, parking, pool access, beach access, WiFi, and plated lunch',
                'price_per_pax' => 1000.00,
                'effective_from' => '2024-12-01',
                'effective_until' => '2024-12-31',
                'is_active' => true,
            ],
            [
                'name' => 'January 2025 Pricing',
                'description' => 'Includes entrance fee, parking, pool access, beach access, WiFi, and plated lunch',
                'price_per_pax' => 950.00,
                'effective_from' => '2025-01-01',
                'effective_until' => null,
                'is_active' => true,
            ],
        ];

        foreach ($pricingData as $data) {
            DayTourPricing::create($data);
        }
    }
}