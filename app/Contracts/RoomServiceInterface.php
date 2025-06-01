<?php
namespace App\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Room;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;

interface RoomServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;
    public function show(int $id): Room;
    public function create(array $data, int $userId): Room;
    public function update(array $data, int $roomId, int $userId): Room;
    public function delete(int $roomId, int $userId): void;
    public function updateStatus(int $roomId, string $newStatus, int $userId): Room;
}
