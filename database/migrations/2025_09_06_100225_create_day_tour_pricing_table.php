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
        Schema::create('day_tour_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "September 2024 Pricing"
            $table->text('description')->nullable(); // e.g., "Includes entrance, parking, pool access, beach access, wifi, plated lunch"
            $table->decimal('price_per_pax', 10, 2); // e.g., 800.00
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['is_active', 'effective_from', 'effective_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('day_tour_pricing');
    }
};