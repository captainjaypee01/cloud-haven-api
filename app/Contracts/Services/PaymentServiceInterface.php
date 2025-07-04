<?php

namespace App\Contracts\Services;

use App\DTO\Payments\PaymentRequestDTO;
use App\DTO\Payments\PaymentResultDTO;

interface PaymentServiceInterface
{
    public function execute(PaymentRequestDTO $dto): PaymentResultDTO;
}
