<?php
namespace App\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Room;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;

interface RoomServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;
    public function create(StoreRoomRequest $request, int $userId): Room;
    public function update(UpdateRoomRequest $request, int $roomId, int $userId): Room;
    public function delete(Room $room, int $userId): void;
    public function updateStatus(Room $room, string $newStatus, int $userId): Room;
}
