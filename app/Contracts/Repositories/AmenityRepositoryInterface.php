<?php
namespace App\Contracts\Repositories;

use App\Models\Amenity;

interface AmenityRepositoryInterface extends RootRepositoryInterface
{
    public function getId(int $id): Amenity;
}
