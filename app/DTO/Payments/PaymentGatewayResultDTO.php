<?php

namespace App\DTO\Payments;

use Spatie\LaravelData\Data;

class PaymentGatewayResultDTO extends Data
{
    public function __construct(
        public bool $success,
        public ?string $errorCode = null,
        public ?string $errorMessage = null
    ) {}
}
