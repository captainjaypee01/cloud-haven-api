<?php

namespace App\Contracts\Room;

use App\Models\Room;
use App\DTO\Rooms\NewRoom;

interface CreateRoomContract
{
    public function handle(NewRoom $payload, int $userId): Room;
}
