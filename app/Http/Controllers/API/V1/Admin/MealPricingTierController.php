<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\UpsertMealPricingTierAction;
use App\Contracts\Repositories\MealPricingTierRepositoryInterface;
use App\DTO\MealPricingTierDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MealPricingTierRequest;
use App\Http\Resources\Admin\MealPricingTierResource;
use App\Http\Responses\ItemResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\EmptyResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class MealPricingTierController extends Controller
{
    public function __construct(
        private MealPricingTierRepositoryInterface $tierRepository,
        private UpsertMealPricingTierAction $upsertAction
    ) {}

    /**
     * Store a newly created pricing tier.
     */
    public function store(MealPricingTierRequest $request, int $programId): ItemResponse|ErrorResponse
    {
        try {
            $data = $request->validated();
            $data['meal_program_id'] = $programId;
            $data['id'] = null;
            
            $dto = MealPricingTierDTO::from($data);

            $tier = $this->upsertAction->execute($dto);

            return new ItemResponse(new MealPricingTierResource($tier), JsonResponse::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Failed to create pricing tier: ' . $e->getMessage());
            return new ErrorResponse('Unable to create pricing tier.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified pricing tier.
     */
    public function update(MealPricingTierRequest $request, int $programId, int $tierId): ItemResponse|ErrorResponse
    {
        try {
            $tier = $this->tierRepository->find($tierId);

            if (!$tier || $tier->meal_program_id !== $programId) {
                return new ErrorResponse('Pricing tier not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $data = $request->validated();
            $data['meal_program_id'] = $programId;
            $data['id'] = $tierId;
            
            $dto = MealPricingTierDTO::from($data);

            $tier = $this->upsertAction->execute($dto);

            return new ItemResponse(new MealPricingTierResource($tier));
        } catch (Exception $e) {
            Log::error('Failed to update pricing tier: ' . $e->getMessage());
            return new ErrorResponse('Unable to update pricing tier.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified pricing tier.
     */
    public function destroy(int $programId, int $tierId): EmptyResponse|ErrorResponse
    {
        try {
            $tier = $this->tierRepository->find($tierId);

            if (!$tier || $tier->meal_program_id !== $programId) {
                return new ErrorResponse('Pricing tier not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $this->tierRepository->delete($tier);

            return new EmptyResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete pricing tier: ' . $e->getMessage());
            return new ErrorResponse('Unable to delete pricing tier.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
