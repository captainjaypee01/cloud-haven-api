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
        Schema::table('promos', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('expires_at');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            
            // Add indexes for promo functionality
            $table->index(['active', 'starts_at', 'ends_at'], 'idx_promo_active_window');
            $table->index(['active', 'expires_at'], 'idx_promo_active_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropIndex('idx_promo_active_window');
            $table->dropIndex('idx_promo_active_expires');
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
