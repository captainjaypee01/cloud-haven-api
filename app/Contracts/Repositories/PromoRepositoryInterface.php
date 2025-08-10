<?php

namespace App\Contracts\Repositories;

use App\Models\Promo;
use Illuminate\Pagination\LengthAwarePaginator;

interface PromoRepositoryInterface extends RootRepositoryInterface
{
    public function getId(int $id): Promo;
    public function getByCode(string $promoCode): Promo;
    public function updateActive(Promo $promo, bool $active): Promo;
    public function updateActiveBulk(array $ids, bool $active): int;
    public function updateExclusive(Promo $promo, bool $exclusive): Promo;
    public function countActiveExclusive(): int;
}
