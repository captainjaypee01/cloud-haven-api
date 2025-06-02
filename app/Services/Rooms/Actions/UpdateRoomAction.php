<?php

namespace App\Services\Rooms\Actions;

use App\Contracts\Room\UpdateRoomContract;
use App\Models\Room;
use App\DTO\Rooms\UpdateRoom;
use Illuminate\Support\Facades\DB;

final class UpdateRoomAction implements UpdateRoomContract
{
    public function handle(Room $room, UpdateRoom $payload, int $userId): Room
    {
        return DB::transaction(fn () => tap($room)->update([
            'name'                  => $payload->name,
            'description'           => $payload->description,
            'quantity'              => $payload->quantity,
            'max_guests'            => $payload->max_guests,
            'extra_guest_fee'       => $payload->extra_guest_fee,
            'base_weekday_rate'     => $payload->base_weekday_rate,
            'base_weekend_rate'     => $payload->base_weekend_rate,
            'status'                => $payload->status,
            'updated_by'            => $userId,
        ]));
    }
}
