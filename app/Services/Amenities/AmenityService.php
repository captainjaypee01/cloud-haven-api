<?php

namespace App\Services\Amenities;

use App\Contracts\Amenities\CreateAmenityContract;
use App\Contracts\Amenities\DeleteAmenityContract;
use App\Contracts\Amenities\UpdateAmenityContract;
use App\Contracts\Repositories\AmenityRepositoryInterface;
use App\Contracts\Services\AmenityServiceInterface;
use App\DTO\Amenities\AmenityDtoFactory;
use App\Models\Amenity;
use Illuminate\Pagination\LengthAwarePaginator;

class AmenityService implements AmenityServiceInterface
{
    public function __construct(
        private readonly AmenityRepositoryInterface $repository,
        private readonly AmenityDtoFactory $dtoFactory,
        private readonly CreateAmenityContract $creator,
        private readonly UpdateAmenityContract $updater,
        private readonly DeleteAmenityContract $deleter,
    ) {}

    /**
     * To get the list of ther Users
     * 
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

    /**
     * Show one Amenity by ID (throws ModelNotFoundException if missing).
     */
    public function show(int $id): Amenity
    {
        return $this->repository->getId($id);
    }

    /**
     * Create a new Amenity.
     */
    public function create(array $data): Amenity
    {
        $dto = $this->dtoFactory->newAmenity($data);
        return $this->creator->handle($dto);
    }

    /**
     * To Update the Amenity by Id
     * 
     * @param int $id
     * @param array $data
     * @return \App\Models\Amenity
     */
    public function update(int $id, array $data): Amenity
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('No update data provided');
        }
        $amenity = $this->repository->getId($id);
        $dto  = $this->dtoFactory->updateAmenity($data);
        return $this->updater->handle($amenity, $dto);
    }

    /**
     * Soft‐archive (delete) the room.
     */
    public function delete(int $id): void
    {
        $amenity = $this->repository->getId($id);
        $this->deleter->handle($amenity);
    }
}
