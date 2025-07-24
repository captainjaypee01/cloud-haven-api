<?php

namespace App\DTO\Promos;

use Spatie\LaravelData\Data;

class UpdatePromo extends Data
{
    public function __construct(
        public string  $code,
        public string  $discount_type,
        public float   $discount_value,
        public ?string $expires_at,
        public ?int    $max_uses,
        public bool    $active = false,       // default inactive on creation
        // Note: We exclude 'uses_count' and 'active' here to manage usage count internally and handle activation via separate endpoint.
    ) {}
}
