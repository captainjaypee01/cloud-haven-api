<?php

namespace App\Repositories;

use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\Models\MealProgram;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MealProgramRepository implements MealProgramRepositoryInterface
{
    public function find(int $id, array $with = []): ?MealProgram
    {
        return MealProgram::with($with)->find($id);
    }

    public function all(array $filters = [], array $with = []): Collection
    {
        $query = MealProgram::with($with);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    public function paginate(array $filters = [], array $with = []): LengthAwarePaginator
    {
        $query = MealProgram::with($with);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        // Handle sorting
        if (isset($filters['sort'])) {
            $sortParts = explode('|', $filters['sort']);
            $column = $sortParts[0] ?? 'updated_at';
            $direction = $sortParts[1] ?? 'desc';
            $query->orderBy($column, $direction);
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $perPage = $filters['per_page'] ?? 10;
        $page = $filters['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getActive(): Collection
    {
        return MealProgram::active()
            ->with(['pricingTiers', 'calendarOverrides'])
            ->orderBy('updated_at', 'desc')
            ->get();
    }

    public function create(array $data): MealProgram
    {
        return MealProgram::create($data);
    }

    public function update(MealProgram $program, array $data): MealProgram
    {
        $program->update($data);
        return $program->fresh();
    }

    public function delete(MealProgram $program): bool
    {
        return $program->delete();
    }
}
