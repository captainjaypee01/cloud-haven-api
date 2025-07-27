<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\MealPriceServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\MealPrice\StoreMealRequest;
use App\Http\Requests\MealPrice\UpdateMealRequest;
use App\Http\Resources\MealPrice\MealPriceCollection;
use App\Http\Resources\MealPrice\MealPriceResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class MealPriceController extends Controller
{
    public function __construct(private MealPriceServiceInterface $mealPriceService) {}
    /** List all promos (with filters, pagination) */
    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->mealPriceService->list($filters);
        return new CollectionResponse(new MealPriceCollection($paginator), JsonResponse::HTTP_OK);
    }

    /** Store a new meal price */
    public function store(StoreMealRequest $request): ItemResponse|ErrorResponse
    {
        try {
            $mealPrice = $this->mealPriceService->create($request->validated());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to create meal price.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new MealPriceResource($mealPrice), JsonResponse::HTTP_CREATED);
    }

    /** Show a specific meal price by ID */
    public function show(int $id): ItemResponse|ErrorResponse
    {
        try {
            $mealPrice = $this->mealPriceService->show($id);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Meal price not found.', JsonResponse::HTTP_NOT_FOUND);
        }
        return new ItemResponse(new MealPriceResource($mealPrice), JsonResponse::HTTP_OK);
    }

    /** Update an existing promo */
    public function update(UpdateMealRequest $request, int $id): ItemResponse|ErrorResponse
    {
        try {
            $mealPrice = $this->mealPriceService->update($id, $request->validated());
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Meal price not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to update meal price.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new MealPriceResource($mealPrice), JsonResponse::HTTP_OK);
    }

    /** Delete a promo */
    public function destroy(int $id): EmptyResponse|ErrorResponse
    {
        try {
            $this->mealPriceService->delete($id);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Meal price not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to delete meal price.', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        return new EmptyResponse(JsonResponse::HTTP_NO_CONTENT);
    }
}
