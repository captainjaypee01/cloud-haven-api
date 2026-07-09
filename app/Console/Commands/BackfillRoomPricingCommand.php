<?php

namespace App\Console\Commands;

use App\Actions\Bookings\PersistBookingRoomNightlyRatesAction;
use App\DTO\RoomNightDTO;
use App\DTO\RoomNightRoomDTO;
use App\DTO\RoomQuoteDTO;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomDailyRate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class BackfillRoomPricingCommand extends Command
{
    protected $signature = 'room-pricing:backfill
                            {--from=2020-01-01 : Start date for seeding daily rates}
                            {--to= : End date for seeding daily rates (defaults to +1 year)}
                            {--bookings-only : Only backfill booking snapshots}
                            {--rates-only : Only seed room daily rates}';

    protected $description = 'Seed room_daily_rates from current room prices and backfill room_quote_data on existing overnight bookings';

    public function __construct(
        private readonly PersistBookingRoomNightlyRatesAction $persistNightlyRates,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $bookingsOnly = (bool) $this->option('bookings-only');
        $ratesOnly = (bool) $this->option('rates-only');

        if (! $bookingsOnly) {
            $this->seedDailyRates();
        }

        if (! $ratesOnly) {
            $this->backfillBookingSnapshots();
        }

        return self::SUCCESS;
    }

    private function seedDailyRates(): void
    {
        $from = Carbon::parse($this->option('from'))->startOfDay();
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfDay()
            : now()->addYear()->startOfDay();

        $count = 0;
        $rooms = Room::where('room_type', 'overnight')->orWhereNull('room_type')->get();

        foreach ($rooms as $room) {
            foreach (CarbonPeriod::create($from, $to) as $date) {
                RoomDailyRate::updateOrCreate(
                    [
                        'room_id' => $room->id,
                        'date' => $date->format('Y-m-d'),
                    ],
                    [
                        'price_per_night' => $room->price_per_night,
                        'is_manual_override' => false,
                    ]
                );
                $count++;
            }
        }

        $this->info("Seeded/updated {$count} room daily rate rows.");
    }

    private function backfillBookingSnapshots(): void
    {
        $bookings = Booking::query()
            ->where(function ($q) {
                $q->where('booking_type', 'overnight')->orWhereNull('booking_type');
            })
            ->whereNull('room_quote_data')
            ->with('bookingRooms.room')
            ->get();

        $updated = 0;
        $mismatches = 0;

        foreach ($bookings as $booking) {
            if ($booking->bookingRooms->isEmpty()) {
                continue;
            }

            $nights = max(1, Carbon::parse($booking->check_in_date)->diffInDays($booking->check_out_date));
            $roomNights = [];
            $period = CarbonPeriod::create(
                Carbon::parse($booking->check_in_date)->startOfDay(),
                Carbon::parse($booking->check_out_date)->startOfDay()->subDay()
            );

            foreach ($period as $date) {
                $nightRooms = [];
                foreach ($booking->bookingRooms as $br) {
                    if (! $br->room) {
                        continue;
                    }
                    $rate = $br->price_per_night > 0
                        ? (float) $br->price_per_night
                        : (float) $br->room->price_per_night;

                    $nightRooms[] = new RoomNightRoomDTO($br->room_id, $br->room->slug, $rate);
                }
                $roomNights[] = new RoomNightDTO($date->format('Y-m-d'), $nightRooms);
            }

            $computedTotal = array_reduce($roomNights, fn ($carry, RoomNightDTO $n) => $carry + $n->nightTotal(), 0.0);
            $storedTotal = (float) $booking->total_price;

            if (abs($computedTotal - $storedTotal) > 0.02 * max(1, $nights)) {
                $mismatches++;
                $this->warn("Booking {$booking->reference_number}: computed {$computedTotal} vs stored {$storedTotal}");
            }

            $quote = new RoomQuoteDTO($roomNights, $storedTotal);
            $booking->update(['room_quote_data' => $quote->toArray()]);

            $booking->load('bookingRooms.room');
            $this->persistNightlyRates->execute($booking, $quote);
            $updated++;
        }

        $this->info("Backfilled room_quote_data on {$updated} bookings ({$mismatches} total mismatches logged).");
    }
}
