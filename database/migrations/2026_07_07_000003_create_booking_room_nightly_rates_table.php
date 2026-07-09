<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_room_nightly_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('rate', 12, 2);
            $table->timestamps();

            $table->unique(['booking_room_id', 'date']);
            $table->index(['booking_id', 'date']);
            $table->index(['room_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_room_nightly_rates');
    }
};
