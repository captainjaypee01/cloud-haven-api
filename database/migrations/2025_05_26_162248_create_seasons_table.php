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
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
        Schema::create('seasonal_room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->nullOnDelete();
            $table->foreignId('season_id')->constrained()->nullOnDelete();
            $table->double('weekday_rate');
            $table->double('weekend_rate');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
