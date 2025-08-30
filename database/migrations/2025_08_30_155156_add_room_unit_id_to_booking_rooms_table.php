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
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->foreignId('room_unit_id')->nullable()->constrained('room_units')->nullOnDelete();
            $table->index('room_unit_id', 'idx_booking_rooms_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->dropForeign(['room_unit_id']);
            $table->dropIndex('idx_booking_rooms_unit_id');
            $table->dropColumn('room_unit_id');
        });
    }
};
