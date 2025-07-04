<?php

namespace App\Actions\Payments;

use App\Contracts\Payments\SimulatePaymentActionInterface;
use App\Contracts\Services\PaymentGatewayInterface;
use App\DTO\Payments\PaymentGatewayResultDTO;
use App\DTO\Payments\PaymentRequestDTO;

class SimulatePaymentAction implements SimulatePaymentActionInterface, PaymentGatewayInterface {
    public function execute(PaymentRequestDTO $dto): PaymentGatewayResultDTO {
        if ($dto->outcome === 'success') {
            return new PaymentGatewayResultDTO(true);
        }
        return new PaymentGatewayResultDTO(false, 'SIM_FAIL', 'Simulated payment failure');
    }
}
