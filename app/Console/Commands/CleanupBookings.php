<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:cleanup {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up all bookings, booking_rooms, and payments data without foreign key constraints';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will delete ALL bookings, booking_rooms, and payments data. Are you sure?')) {
                $this->info('Operation cancelled.');
                return;
            }
        }

        $this->info('Starting cleanup process...');

        try {
            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');

            // Get counts before deletion
            $bookingsCount = DB::table('bookings')->count();
            $bookingRoomsCount = DB::table('booking_rooms')->count();
            $paymentsCount = DB::table('payments')->count();

            $this->info("Found {$bookingsCount} bookings, {$bookingRoomsCount} booking_rooms, and {$paymentsCount} payments to delete.");

            // Truncate tables
            DB::table('payments')->truncate();
            $this->info('✓ Payments table cleaned');

            DB::table('booking_rooms')->truncate();
            $this->info('✓ Booking rooms table cleaned');

            DB::table('bookings')->truncate();
            $this->info('✓ Bookings table cleaned');

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            $this->info('✅ Cleanup completed successfully!');
            $this->info("Deleted {$bookingsCount} bookings, {$bookingRoomsCount} booking_rooms, and {$paymentsCount} payments.");

        } catch (\Exception $e) {
            // Re-enable foreign key checks in case of error
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            
            $this->error('❌ Cleanup failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
