<?php

namespace App\Services;

use App\Contracts\Repositories\RoomRepositoryInterface;
use App\Contracts\Room\CreateRoomContract;
use App\Contracts\Room\DeleteRoomContract;
use App\Contracts\Room\UpdateRoomContract;
use App\Contracts\Room\UpdateStatusContract;
use App\Contracts\Services\RoomPricingServiceInterface;
use App\Contracts\Services\RoomServiceInterface;
use App\Models\Room;
use App\DTO\Rooms\RoomDtoFactory;
use Carbon\Carbon;
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
        private   RoomPricingServiceInterface $roomPricingService,
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
        $room = $this->creator->handle($dto, $userId);

        // Sync images with order
        $attachData = [];
        foreach ($dto->image_ids as $index => $id) {
            $attachData[$id] = ['order' => $index];
        }
        $room->images()->sync($attachData);

        $room->amenities()->sync($dto->amenity_ids);

        return $room;
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
        $updatedRoom = $this->updater->handle($room, $dto, $userId);

        $attachData = [];
        foreach ($dto->image_ids as $index => $id) {
            $attachData[$id] = ['order' => $index];
        }
        $updatedRoom->images()->sync($attachData);

        $updatedRoom->amenities()->sync($dto->amenity_ids);

        return $updatedRoom;
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
     * Get public rooms (overnight only by default)
     */
    public function listPublicRooms(array $filters)
    {
        // Ensure we only show overnight rooms for public listings unless explicitly requested
        if (!isset($filters['room_type'])) {
            $filters['room_type'] = 'overnight';
        }
        
        return $this->query->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

    /**
     * Get List Rooms with Availability
     */
    public function listRoomsWithAvailability(string $start, string $end)
    {
        return $this->query->listRoomsWithAvailability($start, $end);
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
        return $this->query->getAvailableUnits($roomId, $start, $end);
    }

    /**
     * Get detailed availability information for a room
     */
    public function getDetailedAvailability(int $roomId, string $start, string $end): array
    {
        return $this->query->getDetailedAvailability($roomId, $start, $end);
    }

    /**
     * Get public rooms
     */
    public function listFeaturedRooms()
    {
        return $this->query->getFeaturedRooms();
    }

    /**
     * Attach stay-level pricing from the room calendar for public listings.
     */
    public function enrichRoomsWithStayPricing($rooms, string $checkIn, string $checkOut)
    {
        $nights = max(Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)), 1);

        return collect($rooms)->map(function ($room) use ($checkIn, $checkOut, $nights) {
            $quote = $this->roomPricingService->buildQuoteForStay(
                [$room->slug => $room],
                [(object) ['room_id' => $room->slug, 'adults' => 1, 'children' => 0]],
                $checkIn,
                $checkOut
            );

            $room->stay_total = $quote->totalRoom;
            $room->price_per_night_avg = $nights > 0 ? round($quote->totalRoom / $nights, 2) : $quote->totalRoom;
            $room->nightly_rates = array_map(
                fn ($night) => [
                    'date' => $night->date,
                    'rate' => $night->rooms[0]->rate ?? 0,
                ],
                $quote->nights
            );

            return $room;
        });
    }
}
