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
        // Add missing indexes for dashboard performance optimization
        
        // Payments table indexes
        Schema::table('payments', function (Blueprint $table) {
            // Check if indexes don't already exist before adding them
            if (!Schema::hasIndex('payments', 'idx_payments_status_booking')) {
                $table->index(['status', 'booking_id'], 'idx_payments_status_booking');
            }
            
            if (!Schema::hasIndex('payments', 'idx_payments_status')) {
                $table->index('status', 'idx_payments_status');
            }
        });
        
        // Other_charges table indexes
        Schema::table('other_charges', function (Blueprint $table) {
            if (!Schema::hasIndex('other_charges', 'idx_other_charges_booking')) {
                $table->index('booking_id', 'idx_other_charges_booking');
            }
        });
        
        // Bookings table - add composite index for dashboard queries
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasIndex('bookings', 'idx_bookings_status_checkin')) {
                $table->index(['status', 'check_in_date'], 'idx_bookings_status_checkin');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasIndex('payments', 'idx_payments_status_booking')) {
                $table->dropIndex('idx_payments_status_booking');
            }
            
            if (Schema::hasIndex('payments', 'idx_payments_status')) {
                $table->dropIndex('idx_payments_status');
            }
        });
        
        Schema::table('other_charges', function (Blueprint $table) {
            if (Schema::hasIndex('other_charges', 'idx_other_charges_booking')) {
                $table->dropIndex('idx_other_charges_booking');
            }
        });
        
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasIndex('bookings', 'idx_bookings_status_checkin')) {
                $table->dropIndex('idx_bookings_status_checkin');
            }
        });
    }
};