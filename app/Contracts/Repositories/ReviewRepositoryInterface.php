<?php

namespace App\Contracts\Repositories;

use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewRepositoryInterface
{
    public function get(array $filters, ?string $sort = null, int $perPage = 10): LengthAwarePaginator;
    public function findById(int $id): Review;
    public function create(array $data): Review;
    public function update(int $id, array $data): Review;
    public function delete(int $id): bool;
}
