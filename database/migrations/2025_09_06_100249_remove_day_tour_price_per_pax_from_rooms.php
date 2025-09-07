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
        Schema::table('rooms', function (Blueprint $table) {
            // Check if column exists before dropping
            if (Schema::hasColumn('rooms', 'day_tour_price_per_pax')) {
                $table->dropColumn('day_tour_price_per_pax');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->decimal('day_tour_price_per_pax', 10, 2)->nullable()->after('price_per_night');
        });
    }
};