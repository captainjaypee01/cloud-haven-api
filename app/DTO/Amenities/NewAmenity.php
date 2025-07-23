<?php

namespace App\DTO\Amenities;

use Spatie\LaravelData\Data;

class NewAmenity extends Data
{
    public function __construct(
        public string   $name,
        public ?string  $description,
        public ?string  $icon,
        public ?float   $price,
        public ?string  $status,
    ) {}
}
