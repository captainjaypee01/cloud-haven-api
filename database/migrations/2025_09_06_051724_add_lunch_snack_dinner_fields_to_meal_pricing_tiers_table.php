<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meal_pricing_tiers', function (Blueprint $table) {
            // Day Tour lunch pricing
            $table->decimal('adult_lunch_price', 12, 2)->nullable()->after('child_price');
            $table->decimal('child_lunch_price', 12, 2)->nullable()->after('adult_lunch_price');
            
            // PM Snack pricing
            $table->decimal('adult_pm_snack_price', 12, 2)->nullable()->after('child_lunch_price');
            $table->decimal('child_pm_snack_price', 12, 2)->nullable()->after('adult_pm_snack_price');
            
            // Future dinner pricing (not currently used but included for flexibility)
            $table->decimal('adult_dinner_price', 12, 2)->nullable()->after('child_pm_snack_price');
            $table->decimal('child_dinner_price', 12, 2)->nullable()->after('adult_dinner_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_pricing_tiers', function (Blueprint $table) {
            $table->dropColumn([
                'adult_lunch_price',
                'child_lunch_price',
                'adult_pm_snack_price',
                'child_pm_snack_price',
                'adult_dinner_price',
                'child_dinner_price'
            ]);
        });
    }
};