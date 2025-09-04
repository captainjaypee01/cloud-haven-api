<?php

namespace App\Contracts\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Room;
use Illuminate\Support\Collection;

interface RoomServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;
    public function show(int $id): Room;
    public function create(array $data, int $userId): Room;
    public function update(array $data, int $roomId, int $userId): Room;
    public function delete(int $roomId, int $userId): void;
    public function updateStatus(int $roomId, string $newStatus, int $userId): Room;
    public function listPublicRooms(array $filters);
    public function listRoomsWithAvailability(string $start, string $end);
    public function showBySlug(string $slug): Room;
    public function getAvailableRooms(string $start, string $end): mixed;
    public function availableUnits(int $roomId, string $start, string $end): int;
    public function getDetailedAvailability(int $roomId, string $start, string $end): array;
    public function listFeaturedRooms();
}
