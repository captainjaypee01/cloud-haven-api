<?php

namespace App\Services;

use App\Contracts\Repositories\DayTourPricingRepositoryInterface;
use App\Contracts\Services\DayTourPricingServiceInterface;
use App\DTO\DayTourPricing\NewDayTourPricingDTO;
use App\DTO\DayTourPricing\UpdateDayTourPricingDTO;
use App\Models\DayTourPricing;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class DayTourPricingService implements DayTourPricingServiceInterface
{
    public function __construct(
        private readonly DayTourPricingRepositoryInterface $repository
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'effective_from|desc';
        $perPage = $filters['per_page'] ?? 15;
        
        return $this->repository->get($filters, $sort, $perPage);
    }

    public function show(int $id): DayTourPricing
    {
        $pricing = $this->repository->findById($id);
        
        if (!$pricing) {
            throw new ModelNotFoundException('Day Tour Pricing not found.');
        }
        
        return $pricing;
    }

    public function create(NewDayTourPricingDTO $dto): DayTourPricing
    {
        return $this->repository->create($dto->toArray());
    }

    public function update(int $id, UpdateDayTourPricingDTO $dto): DayTourPricing
    {
        $pricing = $this->show($id);
        return $this->repository->update($pricing, $dto->toArray());
    }

    public function delete(int $id): void
    {
        $pricing = $this->show($id);
        $this->repository->delete($pricing);
    }

    public function toggleStatus(int $id): DayTourPricing
    {
        $pricing = $this->show($id);
        return $this->repository->toggleStatus($pricing);
    }

    public function getActivePricingForDate(string $date): ?DayTourPricing
    {
        return $this->repository->getActivePricingForDate($date);
    }

    public function getCurrentActivePricing(): ?DayTourPricing
    {
        return $this->repository->getCurrentActivePricing();
    }
}
