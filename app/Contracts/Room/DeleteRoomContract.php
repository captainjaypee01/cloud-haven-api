<?php

namespace App\Contracts\Room;

use App\Models\Room;

interface DeleteRoomContract
{
    public function handle(Room $room, int $userId): void;
}
