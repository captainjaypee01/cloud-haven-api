<?php

namespace App\Contracts\Repositories;

use App\Models\RoomDailyRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface RoomDailyRateRepositoryInterface
{
    public function findForRoomAndDate(int $roomId, Carbon $date): ?RoomDailyRate;

    public function getForRoomInRange(int $roomId, Carbon $from, Carbon $to): Collection;

    public function upsert(int $roomId, Carbon $date, float $price, bool $isManualOverride): RoomDailyRate;

    public function deleteForRoomAndDate(int $roomId, Carbon $date): bool;
}
