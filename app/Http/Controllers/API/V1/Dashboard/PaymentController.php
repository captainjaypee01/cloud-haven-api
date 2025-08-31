<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\PaymentServiceInterface;
use App\DTO\Payments\PaymentRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Responses\PaymentResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentServiceInterface $paymentService) {}

    public function pay(Request $request, $referenceNumber)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'provider' => 'required|string',
            'outcome' => 'nullable|string',
        ]);

        $dto = new PaymentRequestDTO(
            referenceNumber: $referenceNumber,
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

    public function uploadProof(Request $request, $referenceNumber, $paymentId = null)
    {
        $validated = $request->validate([
            'proof_file' => 'required|file|mimes:jpeg,jpg,png,pdf|max:5120', // 5MB max
            'transaction_id' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
        ]);

        // Find the booking by reference number
        $booking = \App\Models\Booking::where('reference_number', $referenceNumber)->firstOrFail();
        
        // If paymentId is provided, find specific payment; otherwise create new one (legacy behavior)
        if ($paymentId) {
            $payment = \App\Models\Payment::where('id', $paymentId)
                ->where('booking_id', $booking->id)
                ->firstOrFail();
                
            // Use the new proof upload service with additional data
            $proofService = app(\App\Services\PaymentProofService::class);
            $result = $proofService->uploadProof(
                $payment, 
                $validated['proof_file'],
                $validated['transaction_id'] ?? null,
                $validated['remarks'] ?? null
            );
            
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
        
        // Legacy behavior: Create new payment with proof (for backward compatibility)
        $validated = array_merge($validated, $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'provider' => 'required|string',
            'transaction_id' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]));

        // Optimize and store the image locally (public disk)
        $file = $request->file('proof_file') ?? $request->file('proof');
        $storedPath = $this->optimizeAndStoreImage($file, $referenceNumber);

        // Create a pending payment record via manual flow, then attach proof path
        $dto = new PaymentRequestDTO(
            referenceNumber: $referenceNumber,
            amount: (float) $validated['amount'],
            provider: $validated['provider'],
            transactionId: $validated['transaction_id'] ?? null,
            remarks: $validated['remarks'] ?? null,
            isManual: true,
            status: 'pending',
            isNotifyGuest: false,
        );

        try {
            $result = $this->paymentService->execute($dto);
        } catch (ModelNotFoundException $e) {
            // Cleanup stored file if booking not found
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
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
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            throw $e;
        }

        // Attach proof path to created payment and set proof upload tracking
        if ($result->payment && $storedPath) {
            $result->payment->update([
                'proof_image_path' => $storedPath,
                'proof_upload_count' => 1,
                'proof_status' => 'pending',
                'proof_last_file_path' => $storedPath,
                'proof_last_uploaded_at' => now(),
            ]);
            $result->payment->refresh();
        }

        return new PaymentResponse(
            success: true,
            errorCode: null,
            errorMessage: null,
            payment: $result->payment,
            booking: $result->booking,
            status: JsonResponse::HTTP_CREATED
        );
    }

    private function optimizeAndStoreImage(\Illuminate\Http\UploadedFile $file, string $referenceNumber): ?string
    {
        try {
            $maxDimension = 1920;
            $mime = $file->getMimeType();
            $sourcePath = $file->getRealPath();
            [$width, $height] = @getimagesize($sourcePath) ?: [null, null];
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');

            $targetDir = 'payment-proofs/' . preg_replace('/[^A-Za-z0-9\-_.]/', '-', $referenceNumber);
            $filename = now()->format('YmdHis') . '_' . Str::random(8) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            $relativePath = $targetDir . '/' . $filename;

            $shouldResize = $width && $height && ($width > $maxDimension || $height > $maxDimension);

            // If we cannot determine size or mime not supported, store original
            if (!$shouldResize) {
                Storage::disk('public')->putFileAs($targetDir, $file, $filename);
                return $relativePath;
            }

            // Create image resource and resample
            $ratio = min($maxDimension / $width, $maxDimension / $height);
            $newWidth = (int) floor($width * $ratio);
            $newHeight = (int) floor($height * $ratio);
            $dst = imagecreatetruecolor($newWidth, $newHeight);

            // Load source
            $src = null;
            if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) {
                $src = @imagecreatefromjpeg($sourcePath);
            } elseif (str_contains($mime, 'png')) {
                $src = @imagecreatefrompng($sourcePath);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            } elseif (str_contains($mime, 'webp') && function_exists('imagecreatefromwebp')) {
                $src = @imagecreatefromwebp($sourcePath);
            }

            if (!$src) {
                Storage::disk('public')->putFileAs($targetDir, $file, $filename);
                return $relativePath;
            }

            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            // Save to temp and move to storage
            $tmp = tempnam(sys_get_temp_dir(), 'proof_');
            if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) {
                imagejpeg($dst, $tmp, 80);
            } elseif (str_contains($mime, 'png')) {
                imagepng($dst, $tmp, 6);
            } elseif (str_contains($mime, 'webp') && function_exists('imagewebp')) {
                imagewebp($dst, $tmp, 80);
            } else {
                // Fallback to original
                imagedestroy($dst);
                imagedestroy($src);
                Storage::disk('public')->putFileAs($targetDir, $file, $filename);
                return $relativePath;
            }

            imagedestroy($dst);
            imagedestroy($src);

            $stream = fopen($tmp, 'r');
            Storage::disk('public')->put($relativePath, $stream);
            fclose($stream);
            @unlink($tmp);

            return $relativePath;
        } catch (\Throwable $e) {
            Log::warning('Proof image optimization failed: ' . $e->getMessage());
            // Store original if optimization fails
            try {
                $targetDir = 'payment-proofs/' . preg_replace('/[^A-Za-z0-9\-_.]/', '-', $referenceNumber);
                $filename = now()->format('YmdHis') . '_' . Str::random(8) . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');
                Storage::disk('public')->putFileAs($targetDir, $file, $filename);
                return $targetDir . '/' . $filename;
            } catch (\Throwable $e2) {
                Log::error('Failed storing proof image: ' . $e2->getMessage());
                return null;
            }
        }
    }
}
