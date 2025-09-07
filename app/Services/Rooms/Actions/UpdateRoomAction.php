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
            'short_description'     => $payload->short_description,
            'quantity'              => $payload->quantity,
            'max_guests'            => $payload->max_guests,
            'extra_guests'          => $payload->extra_guests,
            'room_type'             => $payload->room_type,
            'base_weekday_rate'     => $payload->base_weekday_rate,
            'base_weekend_rate'     => $payload->base_weekend_rate,
            'price_per_night'       => $payload->price_per_night,
            'status'                => $payload->status,
            'updated_by'            => $userId,
            'is_featured'           => $payload->is_featured,
            'min_guests'            => $payload->min_guests,
        ]));
    }
}
