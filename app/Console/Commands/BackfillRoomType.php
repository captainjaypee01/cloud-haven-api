<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillRoomType extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rooms:backfill-room-type {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill room_type field based on allows_day_use (idempotent)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        // Find rooms that need backfill:
        // - allows_day_use = 1 and room_type is null/empty → set to 'day_tour'
        // - all others remain as 'overnight' (which is the default)
        
        $roomsToBackfill = Room::where('allows_day_use', true)
            ->where(function ($query) {
                $query->whereNull('room_type')
                      ->orWhere('room_type', '');
            })
            ->get();

        if ($roomsToBackfill->isEmpty()) {
            $this->info('No rooms need backfill. All rooms already have room_type set.');
            return 0;
        }

        $this->info(sprintf('Found %d room(s) to backfill:', $roomsToBackfill->count()));
        
        foreach ($roomsToBackfill as $room) {
            $this->line(sprintf(
                '  - Room ID %d (%s): allows_day_use=%s → room_type=day_tour',
                $room->id,
                $room->name,
                $room->allows_day_use ? 'true' : 'false'
            ));
        }

        if (!$isDryRun) {
            if (!$this->confirm('Proceed with backfill?', true)) {
                $this->info('Backfill cancelled.');
                return 0;
            }

            DB::beginTransaction();
            
            try {
                $updated = Room::where('allows_day_use', true)
                    ->where(function ($query) {
                        $query->whereNull('room_type')
                              ->orWhere('room_type', '');
                    })
                    ->update(['room_type' => 'day_tour']);

                DB::commit();
                
                $this->info(sprintf('Successfully updated %d room(s).', $updated));
                $this->info('Backfill completed successfully.');
                
            } catch (\Exception $e) {
                DB::rollback();
                $this->error('Backfill failed: ' . $e->getMessage());
                return 1;
            }
        } else {
            $this->info('Dry run completed. Use --no-dry-run to apply changes.');
        }

        return 0;
    }
}
