<?php

namespace App\Repositories;

use App\Contracts\Repositories\ReviewRepositoryInterface;
use App\Models\Review;
use Illuminate\Pagination\LengthAwarePaginator;

class ReviewRepository implements ReviewRepositoryInterface
{
    public function get(array $filters, ?string $sort = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Review::query()->with(['user', 'room', 'booking']);

        // Filter by type
        if (!empty($filters['type'])) {
            if ($filters['type'] !== 'all') {
                $query->where('type', $filters['type']);
            }
        }

        // Filter by rating
        if (!empty($filters['rating'])) {
            if ($filters['rating'] !== 'all') {
                $query->where('rating', $filters['rating']);
            }
        }

        // Search by comment, first_name, or last_name
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('comment', 'like', "%{$filters['search']}%")
                  ->orWhere('first_name', 'like', "%{$filters['search']}%")
                  ->orWhere('last_name', 'like', "%{$filters['search']}%");
            });
        }

        // Sorting
        if ($sort) {
            [$field, $dir] = explode('|', $sort);
            $query->orderBy($field, $dir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    public function findById(int $id): Review
    {
        return Review::with(['user', 'room', 'booking'])->findOrFail($id);
    }

    public function create(array $data): Review
    {
        return Review::create($data);
    }

    public function update(int $id, array $data): Review
    {
        $review = $this->findById($id);
        $review->update($data);
        return $review->fresh(['user', 'room', 'booking']);
    }

    public function delete(int $id): bool
    {
        $review = $this->findById($id);
        return $review->delete();
    }
}
