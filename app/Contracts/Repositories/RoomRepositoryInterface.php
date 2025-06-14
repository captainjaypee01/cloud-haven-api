<?php

namespace App\Contracts\Repositories;

use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RoomRepositoryInterface extends RootRepositoryInterface
{
    public function getId(int $id): Room;
    public function getBySlug(string $slug): Room;
    public function availableUnits(int $roomId, string $start, string $end): int;
    public function findAvailableRooms(string $start, string $end): mixed;
}
