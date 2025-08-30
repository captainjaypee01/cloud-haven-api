<?php

namespace App\Contracts\Repositories;

use App\Models\Room;
use App\Models\RoomUnit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface RoomUnitRepositoryInterface
{
    /**
     * Get room units with filtering and pagination.
     */
    public function get(
        array $filters,
        ?string $sort = null,
        int $perPage = 10
    ): LengthAwarePaginator;

    /**
     * Get a room unit by ID.
     */
    public function getId(int $id): RoomUnit;

    /**
     * Get all units for a specific room type.
     */
    public function getUnitsForRoom(int $roomId): Collection;

    /**
     * Get available units for a specific room type.
     */
    public function getAvailableUnitsForRoom(int $roomId): Collection;

    /**
     * Find an available unit for booking assignment.
     * Returns the unit with the lowest unit_number for deterministic assignment.
     */
    public function findAvailableUnitForBooking(int $roomId, string $checkInDate, string $checkOutDate): ?RoomUnit;

    /**
     * Create a new room unit.
     */
    public function create(array $data): RoomUnit;

    /**
     * Update a room unit.
     */
    public function update(RoomUnit $roomUnit, array $data): RoomUnit;

    /**
     * Delete a room unit.
     */
    public function delete(RoomUnit $roomUnit): bool;

    /**
     * Generate multiple room units in bulk.
     */
    public function generateUnits(Room $room, array $unitNumbers, bool $skipExisting = false): Collection;

    /**
     * Check if a unit number already exists for a room type.
     */
    public function unitNumberExists(int $roomId, string $unitNumber): bool;
}
