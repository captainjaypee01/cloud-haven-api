<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Return a paginated list of users, possibly filtered/sorted.
     */
    public function get(array $filters, ?string $sort = null, int $perPage = 10): LengthAwarePaginator
    {
        $qb = User::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $qb->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $qb->where('role', $filters['role']);
        }

        if ($sort) {
            // expecting something like "first_name|asc" or "created_at|desc"
            [$column, $dir] = explode('|', $sort);
            $qb->orderBy($column, $dir);
        } else {
            $qb->orderBy('created_at', 'desc');
        }

        return $qb->paginate($perPage);
    }

    /**
     * Find one user by primary key, or throw ModelNotFoundException.
     */
    public function getById(int $id): User
    {
        return User::findOrFail($id);
    }

    /**
     * Find one user by Clerk ID, or null.
     */
    public function findByClerkId(string $clerkId): User
    {
        return User::where('clerk_id', $clerkId)->firstOrFail();
    }
}
