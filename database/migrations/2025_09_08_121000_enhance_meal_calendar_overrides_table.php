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
        Schema::table('meal_calendar_overrides', function (Blueprint $table) {
            $table->enum('override_type', ['date', 'month'])
                  ->default('date')
                  ->after('meal_program_id')
                  ->comment('Type of override: date-specific or month-wide');
            
            $table->unsignedTinyInteger('month')
                  ->nullable()
                  ->after('date')
                  ->comment('Month (1-12) for month-wide overrides');
            
            $table->year('year')
                  ->nullable()
                  ->after('month')
                  ->comment('Year for month-wide overrides');
            
            // Make date nullable since month overrides don't need it
            $table->date('date')->nullable()->change();
            
            // Add index for month-based lookups
            $table->index(['meal_program_id', 'override_type', 'month', 'year'], 'mp_overrides_month_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_calendar_overrides', function (Blueprint $table) {
            $table->dropIndex('mp_overrides_month_idx');
            $table->dropColumn(['override_type', 'month', 'year']);
            $table->date('date')->nullable(false)->change();
        });
    }
};
