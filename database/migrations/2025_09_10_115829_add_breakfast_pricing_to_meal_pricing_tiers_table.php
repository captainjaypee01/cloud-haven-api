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
            $table->decimal('adult_breakfast_price', 8, 2)->nullable()->after('child_dinner_price');
            $table->decimal('child_breakfast_price', 8, 2)->nullable()->after('adult_breakfast_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_pricing_tiers', function (Blueprint $table) {
            $table->dropColumn(['adult_breakfast_price', 'child_breakfast_price']);
        });
    }
};
