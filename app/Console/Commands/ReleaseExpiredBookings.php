<?php

namespace App\Console\Commands;

use App\Contracts\Services\BookingLockServiceInterface;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReleaseExpiredBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:release-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release expired booking locks and cancel bookings not paid in time.';

    /**
     * Execute the console command.
     */
    public function handle(BookingLockServiceInterface $lockService)
    {
        $expired = Booking::where('status', 'pending')
            ->where('reserved_until', '<', Carbon::now())
            ->get();

        $count = 0;
        foreach ($expired as $booking) {
            $lockService->delete($booking->id); // Remove Redis lock (safe even if already expired)
            $booking->status = 'cancelled';
            $booking->save();
            $count++;
        }
        $this->info("Released and cancelled {$count} expired bookings.");
    }
}
