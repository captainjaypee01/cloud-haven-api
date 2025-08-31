<?php

namespace App\Http\Controllers\API\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentProofService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentProofService $paymentProofService) {}

    /**
     * Upload proof of payment for a specific payment
     * POST /v1/user/payments/{paymentId}/proof
     */
    public function uploadProof(Request $request, Payment $payment)
    {
        // Validate file upload and optional fields
        $validated = $request->validate([
            'proof_file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120', // 5MB max
            'transaction_id' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        // Check authorization - ensure payment belongs to user's booking
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error_code' => 'unauthorized',
                'message' => 'Authentication required.'
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Check if payment belongs to user's booking
        // Note: For guest bookings (user_id = null), they should use the guest route instead
        if ($payment->booking->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'error_code' => 'forbidden',
                'message' => 'You can only upload proofs for your own payments. If this is a guest booking, please use the booking reference number to upload proof.'
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        // Handle the proof upload with additional data
        $result = $this->paymentProofService->uploadProof(
            $payment, 
            $request->file('proof_file'),
            $validated['transaction_id'] ?? null,
            $validated['remarks'] ?? null
        );

        // Return appropriate response based on result
        if (!$result['success']) {
            $statusCode = match($result['error_code']) {
                'proof_upload_limit_reached' => JsonResponse::HTTP_TOO_MANY_REQUESTS,
                'upload_failed' => JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
                default => JsonResponse::HTTP_BAD_REQUEST
            };

            return response()->json($result, $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => 'Proof of payment uploaded successfully.',
            'data' => [
                'payment' => $result['payment'],
                'upload_count' => $result['upload_count'],
                'max_uploads' => $result['max_uploads']
            ]
        ]);
    }
}
