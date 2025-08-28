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
        $promos = [
            [
                'code' => 'SPRING2025',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'scope' => 'total',
                'title' => 'Spring Special',
                'description' => 'Enjoy 15% off your entire booking during our spring season!',
                'starts_at' => '2025-03-01 00:00:00',
                'ends_at' => '2025-05-31 23:59:59',
                'expires_at' => '2025-05-31 23:59:59',
                'max_uses' => 100,
                'uses_count' => 0,
                'exclusive' => false,
                'active' => true,
            ],
            [
                'code' => 'EARLYBIRD25',
                'discount_type' => 'percentage',
                'discount_value' => 25,
                'scope' => 'room',
                'title' => 'Early Bird Special',
                'description' => 'Book early and save 25% on room rates!',
                'starts_at' => '2025-01-01 00:00:00',
                'ends_at' => null,
                'expires_at' => '2025-06-30 23:59:59',
                'max_uses' => 50,
                'uses_count' => 0,
                'exclusive' => true,
                'active' => true,
            ],
            [
                'code' => 'STAYCATION1500',
                'discount_type' => 'fixed',
                'discount_value' => 1500,
                'scope' => 'total',
                'title' => 'Staycation Deal',
                'description' => 'Get ₱1,500 off your total booking amount!',
                'starts_at' => null,
                'ends_at' => '2025-12-31 23:59:59',
                'expires_at' => null,
                'max_uses' => null,
                'uses_count' => 0,
                'exclusive' => false,
                'active' => true,
            ],
            [
                'code' => 'WEEKEND500',
                'discount_type' => 'fixed',
                'discount_value' => 500,
                'scope' => 'room',
                'title' => 'Weekend Getaway',
                'description' => 'Save ₱500 on room bookings for weekend stays!',
                'starts_at' => '2025-06-01 00:00:00',
                'ends_at' => '2025-08-31 23:59:59',
                'expires_at' => '2025-08-31 23:59:59',
                'max_uses' => 200,
                'uses_count' => 0,
                'exclusive' => false,
                'active' => true,
            ],
            [
                'code' => 'MEAL20',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'scope' => 'meal',
                'title' => 'Dining Discount',
                'description' => 'Enjoy 20% off on all meal packages!',
                'starts_at' => null,
                'ends_at' => null,
                'expires_at' => '2025-10-31 23:59:59',
                'max_uses' => null,
                'uses_count' => 0,
                'exclusive' => false,
                'active' => true,
            ],
            [
                'code' => 'EXCLUSIVE2500',
                'discount_type' => 'fixed',
                'discount_value' => 2500,
                'scope' => 'total',
                'title' => 'VIP Exclusive',
                'description' => 'Exclusive ₱2,500 discount for our valued guests!',
                'starts_at' => '2025-04-01 00:00:00',
                'ends_at' => '2025-09-30 23:59:59',
                'expires_at' => '2025-09-30 23:59:59',
                'max_uses' => 25,
                'uses_count' => 0,
                'exclusive' => true,
                'active' => true,
            ],
        ];

        foreach ($promos as $promoData) {
            Promo::updateOrCreate(
                ['code' => $promoData['code']],
                $promoData
            );
        }

        $this->command->info('Successfully seeded 6 promotional codes with various configurations.');
        $this->command->info('Promos include: percentage/fixed discounts, different scopes, window restrictions, and exclusive offers.');
    }
}
