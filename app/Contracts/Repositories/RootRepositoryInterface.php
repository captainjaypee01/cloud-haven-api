<?php
namespace App\Contracts\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;

interface RootRepositoryInterface
{
    public function get(array $filters, ?string $sort = null, int $perPage = 10): LengthAwarePaginator;
    public function getId(int $id);
}
