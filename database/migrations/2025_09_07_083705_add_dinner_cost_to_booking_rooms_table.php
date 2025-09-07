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
        Schema::table('booking_rooms', function (Blueprint $table) {
            // Add dinner_cost field for future meal expansions
            $table->decimal('dinner_cost', 10, 2)->default(0)->after('pm_snack_cost');
            
            // Also add include_dinner for consistency
            $table->boolean('include_dinner')->default(false)->after('include_pm_snack');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_rooms', function (Blueprint $table) {
            $table->dropColumn(['dinner_cost', 'include_dinner']);
        });
    }
};