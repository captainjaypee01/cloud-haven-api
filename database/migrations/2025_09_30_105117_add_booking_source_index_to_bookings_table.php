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
        Schema::table('bookings', function (Blueprint $table) {
            // Add index for booking_source to improve calendar queries
            if (!Schema::hasIndex('bookings', 'idx_bookings_source')) {
                $table->index('booking_source', 'idx_bookings_source');
            }
            
            // Add composite index for booking rooms queries with unit_id
            if (!Schema::hasIndex('booking_rooms', 'idx_booking_rooms_unit_booking')) {
                Schema::table('booking_rooms', function (Blueprint $table) {
                    $table->index(['room_unit_id', 'booking_id'], 'idx_booking_rooms_unit_booking');
                });
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasIndex('bookings', 'idx_bookings_source')) {
                $table->dropIndex('idx_bookings_source');
            }
        });
        
        Schema::table('booking_rooms', function (Blueprint $table) {
            if (Schema::hasIndex('booking_rooms', 'idx_booking_rooms_unit_booking')) {
                $table->dropIndex('idx_booking_rooms_unit_booking');
            }
        });
    }
};