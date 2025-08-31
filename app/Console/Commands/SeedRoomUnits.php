<?php

namespace App\Console\Commands;

use Database\Seeders\RoomUnitSeeder;
use Illuminate\Console\Command;

class SeedRoomUnits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:room-units';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed room units based on room quantities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting RoomUnitSeeder...');
        
        $seeder = new RoomUnitSeeder();
        $seeder->setCommand($this);
        $seeder->run();
        
        $this->info('RoomUnitSeeder completed successfully!');
        
        return Command::SUCCESS;
    }
}
