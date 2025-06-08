<?php

namespace App\Contracts\Amenities;

use App\Models\Amenity;
use App\DTO\Amenities\UpdateAmenity;

interface UpdateAmenityContract
{
    public function handle(Amenity $amenity, UpdateAmenity $payload): Amenity;
}
