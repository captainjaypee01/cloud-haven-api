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
        Schema::create('room_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('unit_number'); // e.g., "101", "201", etc.
            $table->enum('status', ['available', 'occupied', 'maintenance', 'blocked'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique constraint: one unit number per room type
            $table->unique(['room_id', 'unit_number'], 'room_unit_number_unique');
            
            // Indexes for efficient queries
            $table->index('room_id', 'idx_room_units_room_id');
            $table->index('status', 'idx_room_units_status');
            $table->index(['room_id', 'status'], 'idx_room_units_room_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_units');
    }
};
