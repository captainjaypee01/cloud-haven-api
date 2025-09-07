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
            // Add fields for Day Tour pricing model
            $table->integer('min_guests')->nullable()->after('max_guests');
            $table->integer('max_guests_range')->nullable()->after('min_guests');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'min_guests',
                'max_guests_range'
            ]);
        });
    }
};