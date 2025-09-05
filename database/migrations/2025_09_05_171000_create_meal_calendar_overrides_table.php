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
        Schema::create('meal_calendar_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_program_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_active')->comment('true = force buffet on; false = force off');
            $table->string('note')->nullable();
            $table->timestamps();
            
            $table->unique(['meal_program_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_calendar_overrides');
    }
};
