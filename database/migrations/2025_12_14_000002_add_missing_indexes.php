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
        Schema::table('amenities', function (Blueprint $table) {
            $table->index('status', 'idx_amenity_status');
        });
        
        Schema::table('amenity_room', function (Blueprint $table) {
            $table->unique(['amenity_id', 'room_id'], 'unique_amenity_room');
        });
        
        Schema::table('rooms', function (Blueprint $table) {
            $table->index('status', 'idx_room_status');
            $table->index('price_per_night', 'idx_room_price');
            $table->index('quantity', 'idx_room_quantity');
        });
        
        Schema::table('bookings', function (Blueprint $table) {
            $table->index('promo_id', 'idx_booking_promo');
            $table->index('user_id', 'idx_booking_user');
            $table->index('paid_at', 'idx_booking_paid_at');
        });
        
        Schema::table('reviews', function (Blueprint $table) {
            $table->index('user_id', 'idx_review_user');
            $table->index('room_id', 'idx_review_room');
            $table->index('type', 'idx_review_type');
            $table->index('rating', 'idx_review_rating');
            $table->index('created_at', 'idx_review_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amenities', function (Blueprint $table) {
            $table->dropIndex('idx_amenity_status');
        });
        
        Schema::table('amenity_room', function (Blueprint $table) {
            $table->dropUnique('unique_amenity_room');
        });
        
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('idx_room_status');
            $table->dropIndex('idx_room_price');
            $table->dropIndex('idx_room_quantity');
        });
        
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_booking_promo');
            $table->dropIndex('idx_booking_user');
            $table->dropIndex('idx_booking_paid_at');
        });
        
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('idx_review_user');
            $table->dropIndex('idx_review_room');
            $table->dropIndex('idx_review_type');
            $table->dropIndex('idx_review_rating');
            $table->dropIndex('idx_review_created');
        });
    }
};
