<?php

namespace App\Contracts\Repositories;

use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RoomRepositoryInterface extends RootRepositoryInterface
{
    public function getId(int $id): Room;
    public function getBySlug(string $slug): Room;
    public function getAvailableUnits(int $roomId, string $start, string $end): int;
    public function getDetailedAvailability(int $roomId, string $start, string $end): array;
    public function findAvailableRooms(string $start, string $end): mixed;
    public function getFeaturedRooms(): Collection;
    public function listRoomsWithAvailability(string $start, string $end);
}
