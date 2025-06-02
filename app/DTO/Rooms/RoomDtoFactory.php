<?php
namespace App\DTO\Rooms;

use App\DTO\Rooms\NewRoom;
use App\DTO\Rooms\UpdateRoom;

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