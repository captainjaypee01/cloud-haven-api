<?php

namespace App\DTO\Promos;

use Spatie\LaravelData\Data;

class NewPromo extends Data
{
    public function __construct(
        public string  $code,
        public string  $title,
        public ?string $description,
        public ?string $scope,
        public string  $discount_type,        // 'fixed' or 'percentage'
        public float   $discount_value,
        public ?string $expires_at,           // nullable date string (Y-m-d)
        public ?int    $max_uses,
        public ?string $image_url,
        public bool    $exclusive = false,
        public ?int    $uses_count = 0,       // default 0 uses initially
        public bool    $active = false,       // default inactive on creation
    ) {}
}
