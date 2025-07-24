<?php

namespace App\Services\Promos;

use App\Contracts\Repositories\PromoRepositoryInterface;
use App\Contracts\Services\PromoServiceInterface;
use App\Contracts\Promos\CreatePromoContract;
use App\Contracts\Promos\UpdatePromoContract;
use App\Contracts\Promos\DeletePromoContract;
use App\DTO\Promos\PromoDtoFactory;
use App\Models\Promo;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class PromoService implements PromoServiceInterface
{
    public function __construct(
        private PromoRepositoryInterface $promoRepository,
        private PromoDtoFactory          $dtoFactory,
        private CreatePromoContract      $creator,
        private UpdatePromoContract      $updater,
        private DeletePromoContract      $deleter,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->promoRepository->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }

    public function show(int $id): Promo
    {
        return $this->promoRepository->getId($id);
    }

    public function create(array $data): Promo
    {
        $dto = $this->dtoFactory->newPromo($data);
        return $this->creator->handle($dto);
    }

    public function update(int $id, array $data): Promo
    {
        if (empty($data)) {
            throw new InvalidArgumentException('No update data provided');
        }
        $promo = $this->promoRepository->getId($id);
        $dto   = $this->dtoFactory->updatePromo($data);
        return $this->updater->handle($promo, $dto);
    }

    public function delete(int $id): void
    {
        $promo = $this->promoRepository->getId($id);
        $this->deleter->handle($promo);
    }

    public function updateStatus(int $id, string $status): Promo
    {
        $promo = $this->promoRepository->getId($id);
        $isActive = ($status === 'active');
        return $this->promoRepository->updateActive($promo, $isActive);
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        $isActive = ($status === 'active');
        return $this->promoRepository->updateActiveBulk($ids, $isActive);
    }
}
