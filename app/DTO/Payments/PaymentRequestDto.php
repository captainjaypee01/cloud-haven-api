<?php

namespace App\DTO\Payments;

use Spatie\LaravelData\Data;

class PaymentRequestDTO extends Data
{
    public function __construct(
        public string $referenceNumber,
        public float $amount,
        public string $provider,
        public ?string $outcome = null
    ) {}
}
