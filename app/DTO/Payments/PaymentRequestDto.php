<?php

namespace App\DTO\Payments;

use Spatie\LaravelData\Data;

class PaymentRequestDTO extends Data
{
    public function __construct(
        public int $bookingId,
        public float $amount,
        public string $provider,
        public ?string $outcome = null
    ) {}
}
