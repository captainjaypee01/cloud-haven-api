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
            $table->enum('room_type', ['overnight', 'day_tour'])
                  ->default('overnight')
                  ->after('quantity');
            
            // Add index for room_type for efficient queries
            $table->index('room_type', 'idx_rooms_room_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('idx_rooms_room_type');
            $table->dropColumn('room_type');
        });
    }
};