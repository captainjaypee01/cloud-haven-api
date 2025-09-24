<?php

namespace App\Services;

use App\Contracts\Repositories\ReviewRepositoryInterface;
use App\Contracts\Services\ReviewServiceInterface;
use App\DTO\Reviews\ReviewDtoFactory;
use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewService implements ReviewServiceInterface
{
    public function __construct(
        protected ReviewRepositoryInterface $repository,
        protected ReviewDtoFactory $dtoFactory
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

    public function create(array $data): Review
    {
        $dto = $this->dtoFactory->newReview($data);
        return $this->repository->create($dto->toArray());
    }

    public function show(int $id): Review
    {
        return $this->repository->findById($id);
    }

    public function update(array $data, int $id): Review
    {
        $dto = $this->dtoFactory->updateReview($data);
        return $this->repository->update($id, $dto->toArray());
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
