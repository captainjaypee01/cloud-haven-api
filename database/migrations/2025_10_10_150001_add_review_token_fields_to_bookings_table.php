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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('review_token', 64)->nullable()->unique()->after('is_reviewed');
            $table->timestamp('review_token_expires_at')->nullable()->after('review_token');
            $table->timestamp('review_email_sent_at')->nullable()->after('review_token_expires_at');
            $table->timestamp('review_token_used_at')->nullable()->after('review_email_sent_at');
            
            // Add indexes for performance
            $table->index('review_token');
            $table->index('review_token_expires_at');
            $table->index('review_email_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['review_token']);
            $table->dropIndex(['review_token_expires_at']);
            $table->dropIndex(['review_email_sent_at']);
            
            $table->dropColumn([
                'review_token',
                'review_token_expires_at',
                'review_email_sent_at',
                'review_token_used_at'
            ]);
        });
    }
};
