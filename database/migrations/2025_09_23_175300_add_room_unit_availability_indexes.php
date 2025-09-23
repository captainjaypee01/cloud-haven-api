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
        // Room units filtered by (room_id, status) and sorted by unit_number
        Schema::table('room_units', function (Blueprint $table) {
            if(!Schema::hasIndex('room_units', 'idx_room_units_room_status')) {
                $table->index(['room_id', 'status'], 'idx_room_units_room_status');
            }
        });

        // BookingRooms join/lookup by unit
        Schema::table('booking_rooms', function (Blueprint $table) {
            if(!Schema::hasIndex('booking_rooms', 'idx_booking_rooms_unit')) {
                $table->index(['room_unit_id', 'booking_id'], 'idx_booking_rooms_unit');
            }
        });

        // Bookings filtered by status/type and date ranges
        // Option A (simple/separate; often good enough):
        Schema::table('bookings', function (Blueprint $table) {
            if(!Schema::hasIndex('bookings', 'idx_bookings_status')) {
                $table->index('status', 'idx_bookings_status');
            }
            if(!Schema::hasIndex('bookings', 'idx_bookings_type')) {
                $table->index('booking_type', 'idx_bookings_type');
            }
            if(!Schema::hasIndex('bookings', 'idx_bookings_check_in')) {
                $table->index('check_in_date', 'idx_bookings_check_in');
            }
            if(!Schema::hasIndex('bookings', 'idx_bookings_check_out')) {
                $table->index('check_out_date', 'idx_bookings_check_out');
            }
        });

        // Option B (if your workload is heavy; test with EXPLAIN):
        // One composite can help with covering reads
        Schema::table('bookings', function (Blueprint $table) {
            if(!Schema::hasIndex('bookings', 'idx_bookings_status_type_dates')) {
                $table->index(['status', 'booking_type', 'check_in_date', 'check_out_date'], 'idx_bookings_status_type_dates');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_units', function (Blueprint $table) {
            if(Schema::hasIndex('room_units', 'idx_room_units_room_status')) {
                $table->dropIndex('idx_room_units_room_status');
            }
        });

        Schema::table('booking_rooms', function (Blueprint $table) {
            if(Schema::hasIndex('booking_rooms', 'idx_booking_rooms_unit')) {
                $table->dropIndex('idx_booking_rooms_unit');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if(Schema::hasIndex('bookings', 'idx_bookings_status')) {
                $table->dropIndex('idx_bookings_status');
            }
            if(Schema::hasIndex('bookings', 'idx_bookings_type')) {
                $table->dropIndex('idx_bookings_type');
            }
            if(Schema::hasIndex('bookings', 'idx_bookings_check_in')) {
                $table->dropIndex('idx_bookings_check_in');
            }
            if(Schema::hasIndex('bookings', 'idx_bookings_check_out')) {
                $table->dropIndex('idx_bookings_check_out');
            }
            if(Schema::hasIndex('bookings', 'idx_bookings_status_type_dates')) {
                $table->dropIndex('idx_bookings_status_type_dates');
            }
        });
    }
};
