<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\PaymentServiceInterface;
use App\Contracts\Services\BookingServiceInterface;
use App\DTO\Payments\PaymentRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\PaymentResponse;
use App\Http\Resources\Payment\PaymentCollection;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentProofService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\EmailTrackingService;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentServiceInterface $paymentService,
        private BookingServiceInterface $bookingService,
        private PaymentProofService $paymentProofService
    ) {}

    /**
     * Display a paginated listing of payments with filtering
     * GET /v1/admin/payments
     */
    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['search', 'status', 'proof_status', 'date', 'from_date', 'to_date', 'sort', 'per_page', 'page']);
        $paginator = $this->paymentService->list($filters);
        
        return new CollectionResponse(new PaymentCollection($paginator), JsonResponse::HTTP_OK);
    }

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

        Log::info('Admin processing payment', [
            'admin_user_id' => Auth::id(),
            'reference_number' => $validated['reference_number'],
            'amount' => $validated['amount'],
            'provider' => $validated['provider'],
            'status' => $validated['status'],
            'transaction_id' => $validated['transaction_id'] ?? null,
            'notify_guest' => $validated['notify_guest'] ?? false
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
        
        if ($result->success) {
            Log::info('Admin payment processed successfully', [
                'admin_user_id' => Auth::id(),
                'reference_number' => $validated['reference_number'],
                'payment_id' => $result->payment->id,
                'amount' => $validated['amount'],
                'status' => $validated['status'],
                'booking_id' => $result->booking->id
            ]);
        } else {
            Log::error('Admin payment processing failed', [
                'admin_user_id' => Auth::id(),
                'reference_number' => $validated['reference_number'],
                'error_code' => $result->errorCode,
                'error_message' => $result->errorMessage,
                'amount' => $validated['amount'],
                'status' => $validated['status']
            ]);
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

        // Store the old status to detect changes
        $oldStatus = $payment->status;
        $newStatus = $validated['status'];
        $notifyGuest = $validated['notify_guest'] ?? true;

        Log::info('Admin updating payment', [
            'admin_user_id' => Auth::id(),
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'booking_reference' => $payment->booking->reference_number,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'amount' => $validated['amount'],
            'provider' => $validated['provider'],
            'notify_guest' => $notifyGuest
        ]);

        // Update the payment
        $payment->update($validated);
        $payment->refresh();

        // Load the booking for status calculations and emails
        $booking = $payment->booking;

        // Handle status change logic and emails
        if ($oldStatus !== $newStatus && $notifyGuest) {
            if ($oldStatus === 'pending' && $newStatus === 'paid') {
                // Store previous booking status before updating
                $previousBookingStatus = $booking->status;
                
                // Payment confirmed: recalculate booking status
                $this->bookingService->markPaid($booking);
                
                // Refresh booking to get updated status
                $booking->refresh();
                
                // Check if booking was just confirmed for the first time
                $isFirstTimeBookingConfirmed = $previousBookingStatus === 'pending' && 
                                             in_array($booking->status, ['downpayment', 'paid']);
                
                if ($isFirstTimeBookingConfirmed) {
                    // First time booking is confirmed - send booking confirmation
                    Mail::to($booking->guest_email)->queue(new \App\Mail\BookingConfirmation($booking, $payment->amount));
                } else {
                    // Subsequent payment or booking already confirmed - send payment success only
                    Mail::to($booking->guest_email)->queue(new \App\Mail\PaymentSuccess($booking, $payment));
                }
                
            } elseif ($oldStatus === 'paid' && $newStatus === 'pending') {
                // Payment reverted: recalculate booking status and send problem notification
                $this->bookingService->markPaid($booking); // This will recalculate based on remaining paid payments
                
                // Send payment problem email only if payment was actually confirmed before
                // (not just proof accepted but payment not yet received)
                Mail::to($booking->guest_email)->queue(new \App\Mail\PaymentProblem($booking, $payment));
                
            } elseif ($newStatus === 'failed') {
                // Payment failed: send failure notification
                Mail::to($booking->guest_email)->queue(new \App\Mail\PaymentFailed($booking, $payment));
            }
        }

        return new EmptyResponse();
    }

    /**
     * Reset proof upload count for a payment
     * PATCH /v1/admin/payments/{payment}/proof-upload/reset
     */
    public function resetProofUploads(Request $request, Payment $payment)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $adminUser = Auth::user();
        $result = $this->paymentProofService->resetProofUploads(
            $payment,
            $request->input('reason'),
            $adminUser?->id
        );

        if (!$result['success']) {
            return response()->json($result, JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => ['payment' => $result['payment']]
        ]);
    }

    /**
     * Update proof status (accept/reject)
     * PATCH /v1/admin/payments/{payment}/proof-status
     */
    public function updateProofStatus(Request $request, Payment $payment)
    {
        $request->validate([
            'status' => 'required|in:accepted,rejected',
            'reason' => 'nullable|string|max:500',
        ]);

        $adminUser = Auth::user();
        $result = $this->paymentProofService->updateProofStatus(
            $payment,
            $request->input('status'),
            $request->input('reason'),
            $adminUser?->id
        );

        if (!$result['success']) {
            $statusCode = match($result['error_code']) {
                'invalid_status' => JsonResponse::HTTP_BAD_REQUEST,
                default => JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            };

            return response()->json($result, $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => ['payment' => $result['payment']]
        ]);
    }
}
