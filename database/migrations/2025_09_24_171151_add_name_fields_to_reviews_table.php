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
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('user_id');
            $table->string('last_name')->nullable()->after('first_name');
            
            // Make existing foreign keys nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->unsignedBigInteger('room_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
            
            // Revert foreign keys to not nullable
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            $table->unsignedBigInteger('room_id')->nullable(false)->change();
        });
    }
};
