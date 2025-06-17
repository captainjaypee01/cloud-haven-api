<?php

namespace App\Services;

use App\Contracts\Repositories\RoomRepositoryInterface;
use App\Contracts\Room\CreateRoomContract;
use App\Contracts\Room\DeleteRoomContract;
use App\Contracts\Room\UpdateRoomContract;
use App\Contracts\Room\UpdateStatusContract;
use App\Contracts\Services\RoomServiceInterface;
use App\Models\Room;
use App\DTO\Rooms\RoomDtoFactory;
use Illuminate\Pagination\LengthAwarePaginator;

class RoomService implements RoomServiceInterface
{

    public function __construct(
        protected RoomRepositoryInterface   $query,
        private   CreateRoomContract        $creator,
        private   UpdateRoomContract        $updater,
        private   DeleteRoomContract        $deleter,
        private   UpdateStatusContract      $statusUpdater,
        private   RoomDtoFactory            $dtoFactory,
    ) {}

    /**
     * List paginated rooms (all statuses or filtered).
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->query->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

    /**
     * Create a new Room.
     */
    public function create(array $data, int $userId): Room
    {
        $dto = $this->dtoFactory->newRoom($data);
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
    public function update(array $data, int $roomId, int $userId): Room
    {
        $room = $this->query->getId($roomId);
        $dto = $this->dtoFactory->updateRoom($data);
        return $this->updater->handle($room, $dto, $userId);
    }

    /**
     * Soft‐archive (delete) the room.
     */
    public function delete($roomId, int $userId): void
    {
        $room = $this->query->getId($roomId);
        $this->deleter->handle($room, $userId);
    }

    /**
     * Only change the room's status.
     */
    public function updateStatus($roomId, string $newStatus, int $userId): Room
    {
        $room = $this->query->getId($roomId);
        return $this->statusUpdater->handle($room, $newStatus, $userId);
    }

    /**
     * Get public rooms
     */
    public function listPublicRooms(array $filters)
    {
        
        return $this->query->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

    /**
     * Get room by Slug
     */
    public function showBySlug(string $slug): Room
    {
        return $this->query->getBySlug($slug);
    }

    /**
     * Get the available room
     */
    public function getAvailableRooms(string $start, string $end): mixed
    {
        return $this->query->findAvailableRooms($start, $end);
    }

    /**
     * Get the available unit of the room based on the selected date
     */
    public function availableUnits(int $roomId, string $start, string $end): int
    {
        return $this->query->availableUnits($roomId, $start, $end);
    }
    
    /**
     * Get public rooms
     */
    public function listFeaturedRooms(array $filters)
    {
        
        return $this->query->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

}
