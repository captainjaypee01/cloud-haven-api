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
use App\Utils\ChangeLogger;

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
        $validatedData = $request->validated();
        
        Log::info('Admin creating new promo code', [
            'admin_user_id' => auth()->id(),
            'promo_code' => $validatedData['code'] ?? null,
            'discount_type' => $validatedData['discount_type'] ?? null,
            'discount_value' => $validatedData['discount_value'] ?? null
        ]);
        
        try {
            $promo = $this->promoService->create($validatedData);
            
            Log::info('Promo code created successfully', [
                'admin_user_id' => auth()->id(),
                'promo_id' => $promo->id,
                'promo_code' => $promo->code,
                'discount_type' => $promo->discount_type,
                'discount_value' => $promo->discount_value
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to create promo code', [
                'admin_user_id' => auth()->id(),
                'promo_code' => $validatedData['code'] ?? null,
                'error' => $e->getMessage()
            ]);
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
        $validatedData = $request->validated();
        
        try {
            // Get original promo data before update
            $originalPromo = $this->promoService->show($id);
            $originalValues = $originalPromo->only(array_keys($validatedData));
            
            ChangeLogger::logUpdateAttempt(
                'Admin updating promo code',
                $originalValues,
                $validatedData,
                [
                    'admin_user_id' => auth()->id(),
                    'promo_id' => $id,
                    'promo_code' => $originalPromo->code
                ]
            );
            
            $promo = $this->promoService->update($id, $validatedData);
            
            ChangeLogger::logSuccessfulUpdate(
                'Promo code updated successfully',
                $originalValues,
                $validatedData,
                [
                    'admin_user_id' => auth()->id(),
                    'promo_id' => $id,
                    'promo_code' => $promo->code
                ]
            );
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Promo not found for update', [
                'admin_user_id' => auth()->id(),
                'promo_id' => $id
            ]);
            return new ErrorResponse('Promo not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            Log::error('Failed to update promo code', [
                'admin_user_id' => auth()->id(),
                'promo_id' => $id,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to update promo.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new PromoResource($promo), JsonResponse::HTTP_OK);
    }

    /** Delete a promo */
    public function destroy(int $id): EmptyResponse|ErrorResponse
    {
        Log::info('Admin deleting promo code', [
            'admin_user_id' => auth()->id(),
            'promo_id' => $id
        ]);
        
        try {
            $this->promoService->delete($id);
            
            Log::info('Promo code deleted successfully', [
                'admin_user_id' => auth()->id(),
                'deleted_promo_id' => $id
            ]);
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Promo not found for deletion', [
                'admin_user_id' => auth()->id(),
                'promo_id' => $id
            ]);
            return new ErrorResponse('Promo not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            Log::error('Failed to delete promo code', [
                'admin_user_id' => auth()->id(),
                'promo_id' => $id,
                'error' => $e->getMessage()
            ]);
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

    /**
     * Toggle the exclusive flag on a promo.  If enabling exclusivity
     * would exceed the maximum allowed, an error message is returned.
     *
     * @param Request $request
     * @param int     $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateExclusive(Request $request, int $id): ErrorResponse|ItemResponse
    {
        $exclusive = $request->input('exclusive');
        // Accept both boolean and string representations of boolean
        if (!is_bool($exclusive)) {
            if (is_string($exclusive)) {
                $exclusive = filter_var($exclusive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            } else {
                return new ErrorResponse('Unable to update promo codes.', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        try {
            $promo = $this->promoService->updateExclusive($id, (bool) $exclusive);
            return new ItemResponse(new PromoResource($promo), JsonResponse::HTTP_OK);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to update promo codes.', JsonResponse::HTTP_BAD_REQUEST);
        }
    }
}
