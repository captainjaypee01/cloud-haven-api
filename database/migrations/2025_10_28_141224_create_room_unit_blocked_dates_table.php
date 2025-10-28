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
        Schema::create('room_unit_blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_unit_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('expiry_date');
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['room_unit_id', 'start_date', 'end_date']);
            $table->index(['active', 'expiry_date']);
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_unit_blocked_dates');
    }
};