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
        Schema::create('meal_prices', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // e.g. 'adult', 'child', 'infant'
            $table->integer('min_age')->nullable();
            $table->integer('max_age')->nullable();
            $table->double('price');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_prices');
    }
};
