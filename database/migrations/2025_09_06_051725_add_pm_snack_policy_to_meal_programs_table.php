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
            $table->enum('pm_snack_policy', ['hidden', 'optional', 'required'])
                  ->default('hidden')
                  ->after('inactive_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meal_programs', function (Blueprint $table) {
            $table->dropColumn('pm_snack_policy');
        });
    }
};