<?php

namespace App\Repositories;

use App\Contracts\Repositories\RoomDailyRateRepositoryInterface;
use App\Models\RoomDailyRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RoomDailyRateRepository implements RoomDailyRateRepositoryInterface
{
    public function findForRoomAndDate(int $roomId, Carbon $date): ?RoomDailyRate
    {
        return RoomDailyRate::where('room_id', $roomId)
            ->whereDate('date', $date->format('Y-m-d'))
            ->first();
    }

    public function getForRoomInRange(int $roomId, Carbon $from, Carbon $to): Collection
    {
        return RoomDailyRate::where('room_id', $roomId)
            ->whereDate('date', '>=', $from->format('Y-m-d'))
            ->whereDate('date', '<=', $to->format('Y-m-d'))
            ->orderBy('date')
            ->get();
    }

    public function upsert(int $roomId, Carbon $date, float $price, bool $isManualOverride): RoomDailyRate
    {
        return RoomDailyRate::updateOrCreate(
            [
                'room_id' => $roomId,
                'date' => $date->format('Y-m-d'),
            ],
            [
                'price_per_night' => $price,
                'is_manual_override' => $isManualOverride,
            ]
        );
    }

    public function deleteForRoomAndDate(int $roomId, Carbon $date): bool
    {
        return (bool) RoomDailyRate::where('room_id', $roomId)
            ->whereDate('date', $date->format('Y-m-d'))
            ->delete();
    }
}
