<?php

namespace App\DTO\Payments;

use Spatie\LaravelData\Data;

class PaymentResultDTO extends Data
{
    public function __construct(
        public bool $success,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public $payment = null,
        public $booking = null
    ) {}
}
