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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->string('provider'); // or 'provider' for future-proofing
            $table->double('amount');
            $table->enum('status', ['pending', 'paid', 'cancelled', 'failed']);
            $table->string('transaction_id')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('response_data')->nullable(); // stores raw API response
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
