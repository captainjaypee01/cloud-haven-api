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
            'short_description'     => $payload->short_description,
            'quantity'              => $payload->quantity,
            'max_guests'            => $payload->max_guests,
            'extra_guests'          => $payload->extra_guests,
            'base_weekday_rate'     => $payload->base_weekday_rate,
            'base_weekend_rate'     => $payload->base_weekend_rate,
            'price_per_night'       => $payload->price_per_night,
            'status'                => $payload->status,
            'created_by'            => $userId,
            'is_featured'           => $payload->is_featured,
        ]));
    }
}
