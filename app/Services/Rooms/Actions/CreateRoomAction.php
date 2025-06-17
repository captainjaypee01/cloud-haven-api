<?php

namespace App\Services\Rooms\Actions;

use App\Contracts\Room\CreateRoomContract;
use App\Models\Room;
use App\DTO\Rooms\NewRoom;
use Illuminate\Support\Facades\DB;

final class CreateRoomAction implements CreateRoomContract
{
    public function handle(NewRoom $payload, int $userId): Room
    {
        return DB::transaction(fn () => Room::create([
            'name'                  => $payload->name,
            'description'           => $payload->description,
            'quantity'              => $payload->quantity,
            'max_guests'            => $payload->max_guests,
            'extra_guest_fee'       => $payload->extra_guest_fee,
            'base_weekday_rate'     => $payload->base_weekday_rate,
            'base_weekend_rate'     => $payload->base_weekend_rate,
            'status'                => $payload->status,
            'created_by'            => $userId,
            'is_featured'           => $payload->is_featured,
        ]));
    }
}
