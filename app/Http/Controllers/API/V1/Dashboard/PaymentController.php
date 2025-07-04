<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\PaymentServiceInterface;
use App\DTO\Payments\PaymentRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Responses\PaymentResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentServiceInterface $paymentService) {}

    public function pay(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'provider' => 'required|string',
            'outcome' => 'nullable|string',
        ]);

        $dto = new PaymentRequestDTO(
            bookingId: $bookingId,
            amount: $validated['amount'],
            provider: $validated['provider'],
            outcome: $validated['outcome'] ?? null
        );
        try {
            $result = $this->paymentService->execute($dto);
        } catch (ModelNotFoundException $e) {
            return new PaymentResponse(
                success: false,
                errorCode: 'NOT_FOUND',
                errorMessage: $e->getMessage(),
                payment: null,
                booking: null,
                status: JsonResponse::HTTP_NOT_FOUND
            );
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            throw $e;
        }

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
            booking: $result->booking,
            status: $status
        );
    }
}
