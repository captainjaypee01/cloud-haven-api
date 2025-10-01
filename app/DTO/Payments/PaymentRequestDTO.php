<?php

namespace App\DTO\Payments;

use Spatie\LaravelData\Data;

class PaymentRequestDTO extends Data
{
    public function __construct(
        public string $referenceNumber,
        public float $amount,
        public string $provider,
        public ?string $outcome = null,
        public ?string $transactionId = null,
        public ?string $remarks = null,
        public bool $isManual = false,
        public ?string $status = null,
        public bool $isNotifyGuest = true,
        public ?string $downpaymentStatus = null,
    ) {}
}
