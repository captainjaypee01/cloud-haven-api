<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing blocked dates to the new table
        DB::statement("
            INSERT INTO room_unit_blocked_dates (room_unit_id, start_date, end_date, expiry_date, active, notes, created_at, updated_at)
            SELECT 
                id as room_unit_id,
                blocked_start_at as start_date,
                blocked_end_at as end_date,
                blocked_end_at as expiry_date, -- Use end_date as expiry for existing records
                true as active,
                CONCAT('Migrated from existing blocked dates - ', notes) as notes,
                created_at,
                updated_at
            FROM room_units 
            WHERE status = 'blocked' 
            AND blocked_start_at IS NOT NULL 
            AND blocked_end_at IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated records (this is a one-way migration)
        DB::table('room_unit_blocked_dates')
            ->where('notes', 'LIKE', 'Migrated from existing blocked dates%')
            ->delete();
    }
};