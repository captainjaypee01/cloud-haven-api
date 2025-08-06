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
}
