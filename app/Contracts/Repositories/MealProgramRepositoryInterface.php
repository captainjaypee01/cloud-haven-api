<?php

namespace App\Contracts\Repositories;

use App\Models\MealProgram;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MealProgramRepositoryInterface
{
    /**
     * Find meal program by ID
     *
     * @param int $id
     * @param array $with
     * @return MealProgram|null
     */
    public function find(int $id, array $with = []): ?MealProgram;

    /**
     * Get all meal programs
     *
     * @param array $filters
     * @param array $with
     * @return Collection
     */
    public function all(array $filters = [], array $with = []): Collection;

    /**
     * Get paginated meal programs
     *
     * @param array $filters
     * @param array $with
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], array $with = []): LengthAwarePaginator;

    /**
     * Get active meal programs
     *
     * @return Collection
     */
    public function getActive(): Collection;

    /**
     * Create a new meal program
     *
     * @param array $data
     * @return MealProgram
     */
    public function create(array $data): MealProgram;

    /**
     * Update a meal program
     *
     * @param MealProgram $program
     * @param array $data
     * @return MealProgram
     */
    public function update(MealProgram $program, array $data): MealProgram;

    /**
     * Delete a meal program
     *
     * @param MealProgram $program
     * @return bool
     */
    public function delete(MealProgram $program): bool;
}
