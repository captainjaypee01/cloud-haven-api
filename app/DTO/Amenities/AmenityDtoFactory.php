<?php
namespace App\DTO\Amenities;

use App\DTO\Amenities\NewAmenity;
use App\DTO\Amenities\UpdateAmenity;

class AmenityDtoFactory
{
    public function newAmenity(array $data): NewAmenity
    {
        return new NewAmenity(
            name: $data['name'],
            description: $data['description'] ?? null,
            icon: $data['icon'] ?? null,
            price: $data['price'] ?? null,
        );
    }
    
    public function updateAmenity(array $data): UpdateAmenity
    {
        return new UpdateAmenity(
            name: $data['name'],
            description: $data['description'] ?? null,
            icon: $data['icon'] ?? null,
            price: $data['price'] ?? null,
        );
    }
}