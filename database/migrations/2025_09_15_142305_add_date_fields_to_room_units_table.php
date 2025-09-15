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
        Schema::table('room_units', function (Blueprint $table) {
            $table->date('maintenance_start_at')->nullable()->after('notes');
            $table->date('maintenance_end_at')->nullable()->after('maintenance_start_at');
            $table->date('blocked_start_at')->nullable()->after('maintenance_end_at');
            $table->date('blocked_end_at')->nullable()->after('blocked_start_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_units', function (Blueprint $table) {
            $table->dropColumn([
                'maintenance_start_at',
                'maintenance_end_at', 
                'blocked_start_at',
                'blocked_end_at'
            ]);
        });
    }
};
