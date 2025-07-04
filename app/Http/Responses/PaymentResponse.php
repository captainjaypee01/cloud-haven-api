<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class PaymentResponse extends Response
{
    public function __construct(
        bool $success,
        ?string $errorCode,
        ?string $errorMessage,
        $payment = null,
        $booking = null,
        int $status = JsonResponse::HTTP_OK
    ) {
        parent::__construct([
            'success' => $success,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'payment' => $payment,
            'booking' => $booking,
        ], $status);
    }
}
