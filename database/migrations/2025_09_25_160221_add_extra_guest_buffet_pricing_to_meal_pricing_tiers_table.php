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
            $table->decimal('adult_extra_guest_fee', 10, 2)->nullable()->after('child_breakfast_price');
            $table->decimal('child_extra_guest_fee', 10, 2)->nullable()->after('adult_extra_guest_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_pricing_tiers', function (Blueprint $table) {
            $table->dropColumn(['adult_extra_guest_fee', 'child_extra_guest_fee']);
        });
    }
};
