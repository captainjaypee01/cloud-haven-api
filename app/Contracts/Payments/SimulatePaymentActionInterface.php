<?php

namespace App\Contracts\Payments;

use App\DTO\Payments\PaymentGatewayResultDTO;
use App\DTO\Payments\PaymentRequestDTO;

interface SimulatePaymentActionInterface
{
    public function execute(PaymentRequestDTO $dto): PaymentGatewayResultDTO;
}
