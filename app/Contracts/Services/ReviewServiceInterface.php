<?php

namespace App\Contracts\Services;

use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReviewServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;
    public function create(array $data): Review;
    public function show(int $id): Review;
    public function update(array $data, int $id): Review;
    public function delete(int $id): bool;
}
