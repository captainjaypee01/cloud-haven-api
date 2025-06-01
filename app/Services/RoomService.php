<?php

namespace App\Services;

use App\Contracts\Room\CreateRoomContract;
use App\Contracts\Room\DeleteRoomContract;
use App\Contracts\Room\UpdateRoomContract;
use App\Contracts\Room\UpdateStatusContract;
use App\Contracts\RoomServiceInterface;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Models\Room;
use App\Queries\RoomQuery;
use App\Services\Rooms\NewRoom;
use App\Services\Rooms\UpdateRoom as UpdateRoomDto;
use Illuminate\Pagination\LengthAwarePaginator;

class RoomService implements RoomServiceInterface
{

    public function __construct(
        protected RoomQuery                 $query,
        private   CreateRoomContract        $creator,
        private   UpdateRoomContract        $updater,
        private   DeleteRoomContract        $deleter,
        private   UpdateStatusContract      $statusUpdater,
    ) {}

    /**
     * List paginated rooms (all statuses or filtered).
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->query->get(
            filters: $filters,
            sort: $filters['sort']   ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

    /**
     * Create a new Room.
     */
    public function create(StoreRoomRequest $request, int $userId): Room
    {
        $dto = NewRoom::from($request->validated());
        return $this->creator->handle($dto, $userId);
    }

    /**
     * Show one Room by ID (throws ModelNotFoundException if missing).
     */
    public function show(int $id): Room
    {
        return $this->query->getId($id);
    }

    /**
     * Update all Room fields.
     */
    public function update(UpdateRoomRequest $request, int $roomId, int $userId): Room
    {
        $room = $this->query->getId($roomId);
        $dto = UpdateRoomDto::from($request->validated());
        return $this->updater->handle($room, $dto, $userId);
    }

    /**
     * Soft‐archive (delete) the room.
     */
    public function delete(Room $room, int $userId): void
    {
        $this->deleter->handle($room, $userId);
    }

    /**
     * Only change the room's status.
     */
    public function updateStatus(Room $room, string $newStatus, int $userId): Room
    {
        return $this->statusUpdater->handle($room, $newStatus, $userId);
    }
}
