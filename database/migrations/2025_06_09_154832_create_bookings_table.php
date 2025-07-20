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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->nullable();
            $table->string('reference_number', 20)->unique(); // add reference number, adjust length as needed
            $table->date('check_in_date');
            $table->time('check_in_time');
            $table->date('check_out_date');
            $table->time('check_out_time');
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('special_requests')->nullable();
            $table->integer('adults');
            $table->integer('children')->default(0);
            $table->integer('total_guests');
            $table->foreignId('promo_id')->nullable()->constrained();
            $table->double('total_price'); // Price for Rooms
            $table->double('meal_price')->default(0); // Price for Meals
            $table->double('discount_amount')->default(0);
            $table->enum('payment_option', ['downpayment', 'full'])->nullable();
            $table->double('downpayment_amount')->nullable();
            $table->double('final_price');
            $table->enum('status', ['pending', 'paid', 'downpayment', 'cancelled', 'failed']);
            $table->unsignedTinyInteger('failed_payment_attempts')->default(0);
            $table->timestamp('last_payment_failed_at')->nullable();
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('downpayment_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Add indexes to bookings migration
            $table->index('reference_number');
            $table->index('status');
            $table->index(['check_in_date', 'check_out_date']); // For availability searches
        });

        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->foreignId('room_id')->constrained();
            $table->double('price_per_night');
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);
            $table->integer('total_guests')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['booking_id', 'room_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_rooms');
        Schema::dropIfExists('bookings');
    }
};
