<?php

namespace App\Contracts\Services;

use App\Models\Promo;
use Illuminate\Pagination\LengthAwarePaginator;

interface PromoServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;
    public function show(int $id): Promo;
    public function create(array $data): Promo;
    public function update(int $id, array $data): Promo;
    public function delete(int $id): void;
    public function updateStatus(int $id, string $status): Promo;
    public function bulkUpdateStatus(array $ids, string $status): int;
}
