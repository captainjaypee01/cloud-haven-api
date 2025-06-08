<?php

namespace App\Contracts\Amenities;

use App\Models\Amenity;
use App\DTO\Amenities\NewAmenity;

interface CreateAmenityContract
{
    public function handle(NewAmenity $payload): Amenity;
}
