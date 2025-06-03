<?php

namespace App\DTO\Users;

use Spatie\LaravelData\Data;

class SyncProviders extends Data
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public ?array $linkedProviders
    ) {}
}
