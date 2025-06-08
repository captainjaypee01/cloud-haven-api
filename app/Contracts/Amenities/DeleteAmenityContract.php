<?php

namespace App\Contracts\Amenities;

use App\Models\Amenity;

interface DeleteAmenityContract
{
    public function handle(Amenity $amenity): void;
}
