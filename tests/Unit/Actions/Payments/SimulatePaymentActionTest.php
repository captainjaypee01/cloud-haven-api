<?php

use App\Actions\Payments\SimulatePaymentAction;
use App\DTO\Payments\PaymentRequestDTO;

describe('SimulatePaymentAction', function () {
    it('returns success for outcome=success', function () {
        $action = new SimulatePaymentAction();
        $dto = new PaymentRequestDTO(1, 1000, 'simulation', 'success');
        $result = $action->execute($dto);
        expect($result->success)->toBeTrue();
    });

    it('returns failure for outcome=fail', function () {
        $action = new SimulatePaymentAction();
        $dto = new PaymentRequestDTO(1, 1000, 'simulation', 'fail');
        $result = $action->execute($dto);
        expect($result->success)->toBeFalse();
    });
});
