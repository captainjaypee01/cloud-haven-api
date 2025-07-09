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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->integer('max_guests');
            $table->integer('extra_guests')->default(2);
            $table->double('extra_guest_fee')->nullable()->default(0.00);
            $table->integer('quantity')->default(1);
            $table->boolean('allows_day_use')->nullable()->default(false);
            $table->double('base_weekday_rate');
            $table->double('base_weekend_rate');
            $table->double('price_per_night');
            $table->tinyInteger('is_featured')->default(0);
            $table->tinyInteger('status')->default(1); // 1= available, 0= unavailable, 2= archived
            $table->foreignId('updated_by')->nullable()->constrained('users', 'id', 'idx_user_update')->nullOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users', 'id', 'idx_user_archive')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', 'id', 'idx_user_create')->nullOnDelete();
            $table->timestamps();

            $table->index(['slug'], 'idx_room_slug');
            $table->index(['is_featured'], 'idx_featured_rooms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
