<?php

namespace App\Contracts\Room;

use App\Models\Room;
use App\Services\Rooms\NewRoom;

interface CreateRoomContract
{
    public function handle(NewRoom $payload, int $userId): Room;
}
