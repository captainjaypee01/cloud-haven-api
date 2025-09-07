<?php

namespace Database\Seeders;

use App\Models\MealCalendarOverride;
use App\Models\MealPricingTier;
use App\Models\MealProgram;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MealProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. October Weekends Buffet (FRI-SUN)
        $octoberProgram = MealProgram::firstOrCreate(
            ['name' => 'October Weekends Buffet'],
            [
                'status' => 'active',
                'scope_type' => 'composite',
                'date_start' => Carbon::create(2025, 10, 1),
                'date_end' => Carbon::create(2025, 10, 31),
                'months' => null,
                'weekdays' => null,
                'weekend_definition' => 'FRI_SUN',
                'inactive_label' => 'Free Breakfast',
                'notes' => 'Demo: Buffet available on Fridays, Saturdays, and Sundays during October 2025',
            ]
        );

        // Add pricing tier for October program
        MealPricingTier::firstOrCreate(
            [
                'meal_program_id' => $octoberProgram->id,
                'currency' => 'PHP',
            ],
            [
                'adult_price' => 1700.00,
                'child_price' => 1000.00,
                'effective_from' => Carbon::create(2025, 10, 1),
                'effective_to' => Carbon::create(2025, 10, 31),
            ]
        );

        // Add a calendar override - force buffet OFF on Oct 15 (even if it's a weekend)
        MealCalendarOverride::firstOrCreate(
            [
                'meal_program_id' => $octoberProgram->id,
                'date' => Carbon::create(2025, 10, 15),
            ],
            [
                'is_active' => false,
                'note' => 'Special event - no buffet service',
            ]
        );

        // 2. Summer Buffet (Mar-May, all days)
        $summerProgram = MealProgram::firstOrCreate(
            ['name' => 'Summer Buffet'],
            [
                'status' => 'active',
                'scope_type' => 'months',
                'date_start' => null,
                'date_end' => null,
                'months' => [3, 4, 5], // March, April, May
                'weekdays' => null,
                'weekend_definition' => 'SAT_SUN',
                'inactive_label' => 'Free Breakfast',
                'notes' => 'Demo: Buffet available on all days during Summer months (March-May)',
            ]
        );

        // Add pricing tiers for Summer program
        // Tier 1: Regular pricing
        MealPricingTier::firstOrCreate(
            [
                'meal_program_id' => $summerProgram->id,
                'currency' => 'PHP',
                'effective_from' => null,
            ],
            [
                'adult_price' => 1700.00,
                'child_price' => 1000.00,
                'effective_to' => null,
            ]
        );

        // Tier 2: Peak season pricing (April only)
        MealPricingTier::firstOrCreate(
            [
                'meal_program_id' => $summerProgram->id,
                'currency' => 'PHP',
                'effective_from' => Carbon::create(2025, 4, 1),
            ],
            [
                'adult_price' => 2000.00,
                'child_price' => 1200.00,
                'effective_to' => Carbon::create(2025, 4, 30),
            ]
        );

        // 3. Year-End Special (Always active during date range)
        $yearEndProgram = MealProgram::firstOrCreate(
            ['name' => 'Year-End Special Buffet'],
            [
                'status' => 'inactive', // Created as inactive for demo
                'scope_type' => 'date_range',
                'date_start' => Carbon::create(2025, 12, 20),
                'date_end' => Carbon::create(2026, 1, 5),
                'months' => null,
                'weekdays' => null,
                'weekend_definition' => 'SAT_SUN',
                'inactive_label' => 'Complimentary Breakfast',
                'notes' => 'Demo: Special buffet pricing for year-end holidays (currently inactive)',
            ]
        );

        // Add pricing tier for Year-End program
        MealPricingTier::firstOrCreate(
            [
                'meal_program_id' => $yearEndProgram->id,
                'currency' => 'PHP',
            ],
            [
                'adult_price' => 1500.00,
                'child_price' => 800.00,
                'effective_from' => null,
                'effective_to' => null,
            ]
        );

        $this->command->info('Meal programs seeded successfully!');
    }
}
