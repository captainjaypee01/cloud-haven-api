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
            $table->ulid()->primary();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('reference_number', 10)->unique(); // add reference number, adjust length as needed
            $table->date('check_in_date');
            $table->time('check_in_time');
            $table->date('check_out_date');
            $table->time('check_out_time');
            $table->string('guest_name')->nullable();
            $table->integer('total_guests');
            $table->foreignId('promo_id')->nullable()->constrained();
            $table->double('total_price'); // Precision for money
            $table->double('discount_amount')->default(0);
            $table->double('final_price');
            $table->enum('status', ['pending', 'confirmed', 'deposit', 'cancelled']);
            $table->timestamp('downpayment_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
            $table->softDeletes(); 
            
            // Add indexes to bookings migration
            $table->index('reference_number');
            $table->index('status');
            $table->index(['check_in_date', 'check_out_date']); // For availability searches
        });

        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('booking_id')->constrained();
            $table->foreignId('room_id')->constrained();
            $table->integer('quantity');
            $table->double('price_per_night');
            $table->timestamps();
            $table->softDeletes();
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
