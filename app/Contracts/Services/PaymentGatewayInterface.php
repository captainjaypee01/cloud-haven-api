<?php

namespace App\Contracts\Services;

use App\DTO\Payments\PaymentGatewayResultDTO;
use App\DTO\Payments\PaymentRequestDTO;

interface PaymentGatewayInterface
{
    public function execute(PaymentRequestDTO $dto): PaymentGatewayResultDTO;
}
