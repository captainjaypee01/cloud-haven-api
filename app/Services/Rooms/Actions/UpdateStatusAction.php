<?php

namespace App\Services\Rooms\Actions;

use App\Contracts\Room\UpdateStatusContract;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

final class UpdateStatusAction implements UpdateStatusContract
{
    public function handle(Room $room, string $newStatus, int $userId): Room
    {
        return DB::transaction(fn () => tap($room)->update(['status' => $newStatus, 'updated_by' => $userId]));
    }
}
