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
        Schema::table('meal_programs', function (Blueprint $table) {
            $table->boolean('buffet_enabled')
                  ->default(true)
                  ->after('pm_snack_policy')
                  ->comment('Whether buffet is enabled for this meal program');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_programs', function (Blueprint $table) {
            $table->dropColumn('buffet_enabled');
        });
    }
};
