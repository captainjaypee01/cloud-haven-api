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
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('image_url', 500)->nullable();
            $table->string('secure_image_url', 500)->nullable();
            $table->string('image_path')->nullable();
            $table->string('provider')->nullable();
            $table->string('public_id')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->tinyInteger('order')->nullable()->default(0);
            $table->string('alt_text')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at');
        });

        Schema::create('room_image', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_image');
        Schema::dropIfExists('images');
    }
};
