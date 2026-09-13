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
        Schema::table('day_tour_pricing', function (Blueprint $table) {
            // Whether this pricing package bundles a complimentary plated lunch.
            // Defaults to true to preserve current behavior for existing rows.
            $table->boolean('includes_plated_lunch')->default(true)->after('price_per_pax');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('day_tour_pricing', function (Blueprint $table) {
            $table->dropColumn('includes_plated_lunch');
        });
    }
};
