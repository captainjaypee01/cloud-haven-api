<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\PromoServiceInterface;
use App\Http\Controllers\Controller;
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
    public function __construct(private PromoServiceInterface $promoService) {}

    public function exclusiveOffers(): CollectionResponse
    {
        $limit  = (int) config('promos.max_exclusive_active', 3);
        $filters = ['status' => 'active', 'exclusive' => true, 'per_page' => $limit];
        $promos = $this->promoService->list($filters);
        return new CollectionResponse(new PromoCollection($promos), JsonResponse::HTTP_OK);
    }


    public function showByCode(string $code): ItemResponse|ErrorResponse
    {
        try {
            $promo = $this->promoService->showByCode($code);

            // Check expiration and usage
            if ($promo->expires_at && now()->gt($promo->expires_at)) {
                return new ErrorResponse('Promo code has expired.', JsonResponse::HTTP_BAD_REQUEST);
            }
            if ($promo->max_uses && $promo->uses_count >= $promo->max_uses) {
                return new ErrorResponse('Promo code is no longer available (usage limit reached).', JsonResponse::HTTP_BAD_REQUEST);
            }

            // Return relevant promo info for frontend
            return new ItemResponse(new PromoResource($promo), JsonResponse::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Promo code not found or inactive.', JsonResponse::HTTP_NOT_FOUND);
        }
    }
}
