<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\PaymentServiceInterface;
use App\DTO\Payments\PaymentRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\PaymentResponse;
use App\Models\Payment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentServiceInterface $paymentService) {}

    public function pay(Request $request)
    {
        $validated = $request->validate([
            'reference_number' => 'required|exists:bookings,reference_number',
            'amount' => 'required|numeric|min:0.01',
            'provider' => 'required|string',
            'transaction_id' => 'nullable|string',
            'remarks' => 'nullable|string',
            'status' => 'required|in:paid,pending,failed',
            'notify_guest' => 'sometimes|boolean',
        ]);

        $dto = new PaymentRequestDTO(
            referenceNumber: $validated['reference_number'],
            amount: $validated['amount'],
            provider: $validated['provider'],
            transactionId: $validated['transaction_id'],
            remarks: $validated['remarks'],
            status: $validated['status'],
            isManual: true,
            isNotifyGuest: $validated['notify_guest'] ?? false,
        );

        $result = $this->paymentService->execute($dto);
        // Decide HTTP status based on result
        $status = JsonResponse::HTTP_OK;

        if (!$result->success) {
            if (in_array($result->errorCode, ['ALREADY_PAID', 'INVALID_STATUS', 'SIM_FAIL'])) {
                $status = JsonResponse::HTTP_BAD_REQUEST;
            } elseif ($result->errorCode === 'VALIDATION_ERROR') {
                $status = JsonResponse::HTTP_UNPROCESSABLE_ENTITY;
            } else {
                $status = JsonResponse::HTTP_INTERNAL_SERVER_ERROR;
            }
        }

        return new PaymentResponse(
            success: $result->success,
            errorCode: $result->errorCode,
            errorMessage: $result->errorMessage,
            payment: $result->payment,
            booking: null,
            status: $status
        );
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'provider' => 'required|string',
            'status' => 'required|string|in:paid,pending,failed',
            'transaction_id' => 'nullable|string',
            'remarks' => 'nullable|string',
            'notify_guest' => 'sometimes|boolean',
        ]);

        $payment->update($validated);
        $payment->refresh();

        return new EmptyResponse();
    }
}
