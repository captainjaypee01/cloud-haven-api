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
        Schema::table('promos', function (Blueprint $table) {
            // Add new fields for flexible promo logic
            $table->json('excluded_days')->nullable()->after('ends_at')->comment('Array of day numbers to exclude (0=Sunday, 1=Monday, ..., 6=Saturday)');
            $table->boolean('per_night_calculation')->default(false)->after('excluded_days')->comment('Whether to apply discount per night vs entire booking');
            
            // Add index for per-night calculation queries
            $table->index(['active', 'per_night_calculation'], 'idx_promo_per_night');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropIndex('idx_promo_per_night');
            $table->dropColumn(['excluded_days', 'per_night_calculation']);
        });
    }
};
