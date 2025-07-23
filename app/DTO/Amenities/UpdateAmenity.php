<?php

namespace App\DTO\Amenities;

use Spatie\LaravelData\Data;

class UpdateAmenity extends Data
{
    public function __construct(
        public ?string  $name = null,       // Make nullable
        public ?string  $description = null,
        public ?string  $icon = null,
        public ?float   $price = null,
        public ?string  $status = 'active',
    ) {}
}
