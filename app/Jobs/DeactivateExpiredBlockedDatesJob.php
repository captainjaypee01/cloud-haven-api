<?php

namespace App\Jobs;

use App\Services\RoomUnitBlockedDateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeactivateExpiredBlockedDatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(RoomUnitBlockedDateService $blockedDateService): void
    {
        try {
            $deactivatedCount = $blockedDateService->deactivateExpiredBlockedDates();
            
            if ($deactivatedCount > 0) {
                Log::info("Background job deactivated {$deactivatedCount} expired blocked dates");
            }
        } catch (\Exception $e) {
            Log::error("Failed to deactivate expired blocked dates in background job", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }
}