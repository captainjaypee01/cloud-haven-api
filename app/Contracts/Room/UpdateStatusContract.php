<?php

namespace App\Contracts\Room;

use App\Models\Room;

interface UpdateStatusContract
{
    public function handle(Room $room, string $newStatus, int $userId): Room;
}
