<?php

namespace App\Console\Commands;

use App\Actions\UpsertMealProgramAction;
use App\Actions\UpsertMealPricingTierAction;
use App\DTO\MealProgramDTO;
use App\DTO\MealPricingTierDTO;
use App\Models\MealPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateLegacyMealPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:meal:migrate-legacy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy meal prices to the new meal program system';

    /**
     * Execute the console command.
     */
    public function handle(
        UpsertMealProgramAction $programAction,
        UpsertMealPricingTierAction $tierAction
    ): int {
        $this->info('Starting migration of legacy meal prices...');

        // Check if we have legacy meal prices
        $legacyPrices = MealPrice::all();
        
        if ($legacyPrices->isEmpty()) {
            $this->info('No legacy meal prices found. Nothing to migrate.');
            return Command::SUCCESS;
        }

        DB::beginTransaction();

        try {
            // Create a disabled legacy program
            $this->info('Creating Legacy Buffet Program (Always Off)...');
            
            $programDto = new MealProgramDTO(
                id: null,
                name: 'Legacy Buffet Program (Always Off)',
                status: 'inactive',
                scopeType: 'always',
                dateStart: null,
                dateEnd: null,
                months: null,
                weekdays: null,
                weekendDefinition: 'SAT_SUN',
                inactiveLabel: 'Free Breakfast',
                notes: 'Migrated from legacy meal_prices table. This program is always inactive to maintain backwards compatibility.'
            );

            $program = $programAction->execute($programDto);
            $this->info("Created meal program: {$program->name} (ID: {$program->id})");

            // Migrate each meal price as a pricing tier
            foreach ($legacyPrices as $legacyPrice) {
                $this->info("Migrating meal price ID: {$legacyPrice->id}...");
                
                $tierDto = new MealPricingTierDTO(
                    id: null,
                    mealProgramId: $program->id,
                    currency: $legacyPrice->currency ?? 'SGD',
                    adultPrice: (float) $legacyPrice->adult_price,
                    childPrice: (float) $legacyPrice->child_price,
                    effectiveFrom: $legacyPrice->created_at,
                    effectiveTo: null
                );

                $tier = $tierAction->execute($tierDto);
                $this->info("  - Created pricing tier: {$tier->currency} Adult: {$tier->adult_price}, Child: {$tier->child_price}");
            }

            // Mark legacy code paths as deprecated (add comment to meal_prices table)
            DB::statement("ALTER TABLE meal_prices COMMENT = 'DEPRECATED: Use meal_programs, meal_pricing_tiers, and meal_calendar_overrides tables instead'");

            DB::commit();

            $this->info('Migration completed successfully!');
            $this->warn('Note: The legacy program is created as INACTIVE. To enable meal pricing:');
            $this->warn('1. Create a new active meal program with desired scope');
            $this->warn('2. Add pricing tiers to the new program');
            $this->warn('3. Configure calendar rules as needed');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
