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
        Schema::table('payments', function (Blueprint $table) {
            // Proof upload tracking columns
            $table->integer('proof_upload_count')->default(0)->after('response_data');
            $table->integer('proof_upload_generation')->default(1)->after('proof_upload_count');
            $table->enum('proof_status', ['none', 'pending', 'accepted', 'rejected'])->default('none')->after('proof_upload_generation');
            $table->string('proof_last_file_path')->nullable()->after('proof_status');
            $table->text('proof_rejected_reason')->nullable()->after('proof_last_file_path');
            $table->unsignedBigInteger('proof_rejected_by')->nullable()->after('proof_rejected_reason');
            $table->timestamp('last_proof_notification_at')->nullable()->after('proof_rejected_by');
            $table->timestamp('proof_last_uploaded_at')->nullable()->after('last_proof_notification_at');

            // Foreign key for the admin user who rejected
            $table->foreign('proof_rejected_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['proof_rejected_by']);
            $table->dropColumn([
                'proof_upload_count',
                'proof_upload_generation',
                'proof_status',
                'proof_last_file_path',
                'proof_rejected_reason',
                'proof_rejected_by',
                'last_proof_notification_at',
                'proof_last_uploaded_at'
            ]);
        });
    }
};
