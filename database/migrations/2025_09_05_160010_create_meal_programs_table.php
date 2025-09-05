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
        Schema::create('meal_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->enum('scope_type', ['always', 'date_range', 'months', 'weekly', 'composite']);
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->json('months')->nullable(); // array of integers 1-12
            $table->json('weekdays')->nullable(); // array of MON..SUN
            $table->enum('weekend_definition', ['SAT_SUN', 'FRI_SUN', 'CUSTOM'])->default('SAT_SUN');
            $table->string('inactive_label')->default('Free Breakfast');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_programs');
    }
};
