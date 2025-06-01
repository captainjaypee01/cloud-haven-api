<?php
namespace App\Services\Rooms;

use App\Services\Rooms\NewRoom;
use App\Services\Rooms\UpdateRoom;

class RoomDtoFactory
{
    public function newRoom(array $data): NewRoom
    {
        return NewRoom::from($data);
    }
    
    public function updateRoom(array $data): UpdateRoom
    {
        return UpdateRoom::from($data);
    }
}