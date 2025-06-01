<?php

namespace App\Contracts\Room;

use App\Models\Room;
use App\Services\Rooms\UpdateRoom;

interface UpdateRoomContract
{
    public function handle(Room $room, UpdateRoom $payload, int $userId): Room;
}
