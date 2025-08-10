<?php

namespace App\Services\Promos;

use App\Contracts\Repositories\PromoRepositoryInterface;
use App\Contracts\Services\PromoServiceInterface;
use App\Contracts\Promos\CreatePromoContract;
use App\Contracts\Promos\UpdatePromoContract;
use App\Contracts\Promos\DeletePromoContract;
use App\DTO\Promos\PromoDtoFactory;
use App\Models\Promo;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

    public function showByCode(string $promoCode): Promo
    {
        return $this->promoRepository->getByCode($promoCode);
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

    public function updateExclusive(int $id, bool $exclusive): Promo
    {
        return DB::transaction(function () use ($id, $exclusive) {
            // When enabling exclusive, enforce the maximum number of
            // exclusive offers defined in config.  If the promo is
            // already exclusive or we are disabling it, no check is
            // required.
            $promo = $this->promoRepository->getId($id);
            if ($exclusive) {
                $currentCount = $this->promoRepository->countActiveExclusive();
                $maxAllowed   = (int) config('promos.max_exclusive_active', 3);
                // Retrieve the promo to check its current exclusive state
                if (!$promo->exclusive && $currentCount >= $maxAllowed) {
                    throw new Exception('Maximum number of exclusive promos reached.');
                }
            }
            return $this->promoRepository->updateExclusive($promo, $exclusive);
        });
    }
}
