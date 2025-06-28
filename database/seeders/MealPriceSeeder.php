<?php

namespace Database\Seeders;

use App\Models\MealPrice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MealPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        MealPrice::updateOrCreate(
            [
                'category'  => 'adult',
            ],
            [
                'min_age'   => 7,
                'price'     => 1700,
            ]
        );
        MealPrice::updateOrCreate(
            [
                'category'  => 'children',
            ],
            [
                'min_age'   => 4,
                'max_age'   => 6,
                'price'     => 1000,
            ]
        );
        MealPrice::updateOrCreate(
            [
                'category'  => 'infant',
            ],
            [
                'min_age'   => 0,
                'max_age'   => 3,
                'price'     => 0,
            ]
        );
    }
}
