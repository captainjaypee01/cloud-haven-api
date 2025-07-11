<?php
namespace App\Contracts\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\User;

interface UserRepositoryInterface extends RootRepositoryInterface
{
    public function get(array $filters, ?string $sort = null, int $perPage = 10): LengthAwarePaginator;
    public function getId(int $id): User;
    public function findByClerkId(string $clerkId): User;
}
