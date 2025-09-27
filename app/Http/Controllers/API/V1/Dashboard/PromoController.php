<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\PromoServiceInterface;
use App\Http\Controllers\Controller;
use App\Services\PromoCalculationService;
use Illuminate\Http\Request;
use App\Http\Resources\Promo\PromoCollection;
use App\Http\Resources\Promo\PromoResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;

class PromoController extends Controller
{
    public function __construct(
        private PromoServiceInterface $promoService,
        private PromoCalculationService $promoCalculationService
    ) {}

    public function exclusiveOffers(): CollectionResponse
    {
        $limit  = (int) config('promos.max_exclusive_active', 3);
        $filters = ['status' => 'active', 'exclusive' => true, 'per_page' => $limit];
        $promos = $this->promoService->list($filters);
        return new CollectionResponse(new PromoCollection($promos), JsonResponse::HTTP_OK);
    }


    public function showByCode(Request $request, string $code): ItemResponse|ErrorResponse
    {
        try {
            $promo = $this->promoService->showByCode($code);

            // Check if promo is active
            if (!$promo->active) {
                return new ErrorResponse('Promo code is not active.', JsonResponse::HTTP_BAD_REQUEST);
            }

            // Get booking dates from request (optional parameters)
            $checkInDate = $request->query('check_in_date');
            $checkOutDate = $request->query('check_out_date');
            $dayTourDate = $request->query('day_tour_date');

            // Determine the booking date to validate against (for start/end date validation)
            $bookingDate = null;
            if ($dayTourDate) {
                $bookingDate = \Carbon\Carbon::parse($dayTourDate)->startOfDay();
            } elseif ($checkInDate) {
                $bookingDate = \Carbon\Carbon::parse($checkInDate)->startOfDay();
            }

            // For start/end date validation, use booking date or current date
            $validationDate = $bookingDate ?: now()->startOfDay();
            
            // For expiration check, always use current date (date only)
            $currentDate = now()->startOfDay();

            // Check if promo has started (date only comparison)
            if ($promo->starts_at && $validationDate->lt($promo->starts_at->startOfDay())) {
                return new ErrorResponse('Promo code is not yet active for your selected dates.', JsonResponse::HTTP_BAD_REQUEST);
            }

            // Check if promo has ended (date only comparison)
            if ($promo->ends_at && $validationDate->gt($promo->ends_at->startOfDay())) {
                return new ErrorResponse('Promo code has ended before your selected dates.', JsonResponse::HTTP_BAD_REQUEST);
            }

            // Check expiration against current date (not booking date)
            if ($promo->expires_at && $currentDate->gt($promo->expires_at->startOfDay())) {
                return new ErrorResponse('Promo code has expired.', JsonResponse::HTTP_BAD_REQUEST);
            }
            if ($promo->max_uses && $promo->uses_count >= $promo->max_uses) {
                return new ErrorResponse('Promo code is no longer available (usage limit reached).', JsonResponse::HTTP_BAD_REQUEST);
            }

            // Use new promo validation service for per-night logic
            if ($checkInDate && $checkOutDate) {
                $validation = $this->promoCalculationService->validatePromoForDateRange($promo, $checkInDate, $checkOutDate);
                if (!$validation['is_valid']) {
                    return new ErrorResponse(implode(' ', $validation['errors']), JsonResponse::HTTP_BAD_REQUEST);
                }
            }

            // Check if this is a Day Tour booking and validate promo scope
            $isDayTourBooking = !empty($dayTourDate) && empty($checkInDate);
            if ($isDayTourBooking && $promo->scope !== 'total') {
                return new ErrorResponse(
                    "Promo code '{$promo->code}' cannot be used for Day Tour bookings. Only total discount promos are supported for Day Tours.",
                    JsonResponse::HTTP_BAD_REQUEST
                );
            }

            // Return relevant promo info for frontend
            return new ItemResponse(new PromoResource($promo), JsonResponse::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Promo code not found or inactive.', JsonResponse::HTTP_NOT_FOUND);
        }
    }
}
