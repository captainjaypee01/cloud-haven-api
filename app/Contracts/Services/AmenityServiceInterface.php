<?php
namespace App\Contracts\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Amenity;

interface AmenityServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;
    public function show(int $id): Amenity;
    public function create(array $data): Amenity;
    public function update(int $amenityId, array $data): Amenity;
    public function delete(int $amenityId): void;
}
