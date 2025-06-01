<?php

namespace App\Services\Rooms\Actions;

use App\Contracts\Room\DeleteRoomContract;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

final class DeleteRoomAction implements DeleteRoomContract
{
    public function handle(Room $room, int $userId): void
    {
        // Soft‐archive (no hard delete) 
        DB::transaction(fn () => $room->update(['status' => 'archived', 'archived_by' => $userId]));
    }
}
