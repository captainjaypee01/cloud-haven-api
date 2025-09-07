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
            // Day Tour meal selections
            $table->boolean('include_lunch')->default(false)->after('total_guests');
            $table->boolean('include_pm_snack')->default(false)->after('include_lunch');
            
            // Meal pricing breakdown
            $table->decimal('lunch_cost', 10, 2)->default(0)->after('include_pm_snack');
            $table->decimal('pm_snack_cost', 10, 2)->default(0)->after('lunch_cost');
            $table->decimal('meal_cost', 10, 2)->default(0)->after('pm_snack_cost');
            
            // Day Tour specific pricing
            $table->decimal('base_price', 10, 2)->nullable()->after('meal_cost')->comment('Base price before meals');
            $table->decimal('total_price', 10, 2)->nullable()->after('base_price')->comment('Total price including meals');
        });
        
        // Also add a booking type to bookings table to easily identify Day Tour bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('booking_type', ['overnight', 'day_tour'])->default('overnight')->after('reference_number');
            $table->index('booking_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->dropColumn([
                'include_lunch',
                'include_pm_snack',
                'lunch_cost',
                'pm_snack_cost',
                'meal_cost',
                'base_price',
                'total_price'
            ]);
        });
        
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['booking_type']);
            $table->dropColumn('booking_type');
        });
    }
};