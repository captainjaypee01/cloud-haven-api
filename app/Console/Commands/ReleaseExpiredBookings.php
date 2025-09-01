<?php

namespace App\Console\Commands;

use App\Contracts\Services\BookingLockServiceInterface;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

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
    protected $description = 'Release expired booking locks and cancel bookings without accepted proof of payment.';

    /**
     * Execute the console command.
     */
    public function handle(BookingLockServiceInterface $lockService)
    {
        // Find bookings that should be cancelled based on the following criteria:
        // 1. Status is 'pending'
        // 2. Past the initial hold period (reserved_until)
        // 3. AND either:
        //    a) No payments exist at all, OR
        //    b) All payments are rejected AND grace period has expired (2 days after last rejection)
        
        $gracePeriodDays = config('booking.proof_rejection_grace_period_days', 2);
        $gracePeriodCutoff = Carbon::now()->subDays($gracePeriodDays);
        
        $expired = Booking::where('status', 'pending')
            ->where('reserved_until', '<', Carbon::now())
            ->where(function ($query) use ($gracePeriodCutoff) {
                // No payments exist for this booking
                $query->whereDoesntHave('payments')
                    // OR all payments are rejected and grace period has expired
                    ->orWhere(function ($subQuery) use ($gracePeriodCutoff) {
                        $subQuery->whereDoesntHave('payments', function ($paymentQuery) {
                            $paymentQuery->where('proof_status', '!=', 'rejected');
                        })->whereHas('payments', function ($paymentQuery) use ($gracePeriodCutoff) {
                            $paymentQuery->where('proof_status', 'rejected')
                                ->where('proof_rejected_at', '<', $gracePeriodCutoff);
                        });
                    });
            })
            ->get();

        $count = 0;
        foreach ($expired as $booking) {
            $lockService->delete($booking->id); // Remove Redis lock (safe even if already expired)
            $booking->status = 'cancelled';
            $booking->save();
            
            // Send cancellation email notification (automatic cancellation)
            Mail::to($booking->guest_email)->queue(new \App\Mail\BookingCancelled($booking, null, false));
            
            $count++;
        }
        
        $this->info("Released and cancelled {$count} expired bookings without payment or after grace period expired.");
    }
}
