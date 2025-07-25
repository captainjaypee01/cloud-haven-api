<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\PromoServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promo\StorePromoRequest;
use App\Http\Requests\Promo\UpdatePromoRequest;
use App\Http\Resources\Promo\PromoResource;
use App\Http\Resources\Promo\PromoCollection;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\ItemResponse;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use Exception;

class PromoController extends Controller
{
    public function __construct(private PromoServiceInterface $promoService) {}

    /** List all promos (with filters, pagination) */
    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->promoService->list($filters);
        return new CollectionResponse(new PromoCollection($paginator), JsonResponse::HTTP_OK);
    }

    /** Store a new promo */
    public function store(StorePromoRequest $request): ItemResponse|ErrorResponse
    {
        try {
            $promo = $this->promoService->create($request->validated());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to create promo code.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new PromoResource($promo), JsonResponse::HTTP_CREATED);
    }

    /** Show a specific promo by ID */
    public function show(int $id): ItemResponse|ErrorResponse
    {
        try {
            $promo = $this->promoService->show($id);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Promo not found.', JsonResponse::HTTP_NOT_FOUND);
        }
        return new ItemResponse(new PromoResource($promo), JsonResponse::HTTP_OK);
    }

    /** Update an existing promo */
    public function update(UpdatePromoRequest $request, int $id): ItemResponse|ErrorResponse
    {
        try {
            $promo = $this->promoService->update($id, $request->validated());
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Promo not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to update promo.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new PromoResource($promo), JsonResponse::HTTP_OK);
    }

    /** Delete a promo */
    public function destroy(int $id): EmptyResponse|ErrorResponse
    {
        try {
            $this->promoService->delete($id);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Promo not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            // If PromoInUseException was thrown, it will be caught here as generic Exception.
            // For simplicity, we return a generic message (could also inspect $e for specific exception).
            return new ErrorResponse('Unable to delete promo.', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        return new EmptyResponse(JsonResponse::HTTP_NO_CONTENT);
    }

    /** Toggle a single promo's active status */
    public function updateStatus(Request $request, int $id): ItemResponse|ErrorResponse
    {
        $validated = $request->validate([
            'status' => 'required|string',
        ]);
        try {
            $promo = $this->promoService->updateStatus($id, $validated['status']);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Promo not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to update promo status.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new PromoResource($promo), JsonResponse::HTTP_OK);
    }

    /** Bulk activate/deactivate multiple promos */
    public function bulkUpdateStatus(Request $request): EmptyResponse|ErrorResponse
    {
        $validated = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|distinct',
            'status' => 'required|string',
        ]);
        try {
            $this->promoService->bulkUpdateStatus($validated['ids'], $validated['status']);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to update promo codes.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        // We can return no content or a count; here just return 204 No Content with success.
        return new EmptyResponse(JsonResponse::HTTP_NO_CONTENT);
    }
}
