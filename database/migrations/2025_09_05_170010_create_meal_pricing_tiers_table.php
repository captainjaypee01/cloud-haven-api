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
        Schema::create('meal_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_program_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('adult_price', 12, 2);
            $table->decimal('child_price', 12, 2);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            
            $table->index(['meal_program_id', 'effective_from', 'effective_to'], 'mp_tiers_program_dates_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_pricing_tiers');
    }
};
