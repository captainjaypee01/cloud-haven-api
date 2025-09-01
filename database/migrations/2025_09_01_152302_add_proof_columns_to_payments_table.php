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
            // Only add columns that don't already exist
            if (!Schema::hasColumn('payments', 'proof_upload_count')) {
                $table->integer('proof_upload_count')->default(0)->after('response_data');
            }
            if (!Schema::hasColumn('payments', 'proof_upload_generation')) {
                $table->integer('proof_upload_generation')->default(1)->after('proof_upload_count');
            }
            if (!Schema::hasColumn('payments', 'proof_status')) {
                $table->enum('proof_status', ['none', 'pending', 'accepted', 'rejected'])->default('none')->after('proof_upload_generation');
            }
            if (!Schema::hasColumn('payments', 'proof_last_file_path')) {
                $table->string('proof_last_file_path')->nullable()->after('proof_status');
            }
            if (!Schema::hasColumn('payments', 'proof_rejected_reason')) {
                $table->text('proof_rejected_reason')->nullable()->after('proof_last_file_path');
            }
            if (!Schema::hasColumn('payments', 'proof_rejected_by')) {
                $table->unsignedBigInteger('proof_rejected_by')->nullable()->after('proof_rejected_reason');
            }
            if (!Schema::hasColumn('payments', 'last_proof_notification_at')) {
                $table->timestamp('last_proof_notification_at')->nullable()->after('proof_rejected_by');
            }
            if (!Schema::hasColumn('payments', 'proof_last_uploaded_at')) {
                $table->timestamp('proof_last_uploaded_at')->nullable()->after('last_proof_notification_at');
            }
        });

        // Add foreign key if the column was added and foreign key doesn't exist
        if (Schema::hasColumn('payments', 'proof_rejected_by')) {
            try {
                Schema::table('payments', function (Blueprint $table) {
                    $table->foreign('proof_rejected_by')->references('id')->on('users')->onDelete('set null');
                });
            } catch (\Exception $e) {
                // Foreign key might already exist, ignore error
                \Log::info('Foreign key for proof_rejected_by already exists or couldn\'t be created: ' . $e->getMessage());
            }
        }
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
