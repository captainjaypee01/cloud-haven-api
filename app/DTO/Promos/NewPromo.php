<?php

namespace App\DTO\Promos;

use Spatie\LaravelData\Data;

class NewPromo extends Data
{
    public function __construct(
        public string  $code,
        public string  $discount_type,        // 'fixed' or 'percentage'
        public float   $discount_value,
        public ?string $expires_at,           // nullable timestamp string
        public ?int    $max_uses,
        public ?int    $uses_count = 0,       // default 0 uses initially
        public bool    $active = false,       // default inactive on creation
    ) {}
}
