<?php

namespace App\Console\Commands;

use App\Jobs\DeactivateExpiredBlockedDatesJob;
use Illuminate\Console\Command;

class DeactivateExpiredBlockedDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blocked-dates:deactivate-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate expired blocked dates for room units';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting deactivation of expired blocked dates...');
        
        try {
            // Dispatch the job
            DeactivateExpiredBlockedDatesJob::dispatch();
            
            $this->info('Job dispatched successfully. Check the logs for execution details.');
            
        } catch (\Exception $e) {
            $this->error('Failed to dispatch job: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
