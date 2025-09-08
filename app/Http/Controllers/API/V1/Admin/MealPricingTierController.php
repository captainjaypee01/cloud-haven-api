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
        $validatedData = $request->validated();
        
        Log::info('Admin creating new meal pricing tier', [
            'admin_user_id' => auth()->id(),
            'program_id' => $programId,
            'tier_name' => $validatedData['name'] ?? null,
            'age_min' => $validatedData['age_min'] ?? null,
            'age_max' => $validatedData['age_max'] ?? null
        ]);
        
        try {
            $data = $validatedData;
            $data['meal_program_id'] = $programId;
            $data['id'] = null;
            
            $dto = MealPricingTierDTO::from($data);

            $tier = $this->upsertAction->execute($dto);

            Log::info('Meal pricing tier created successfully', [
                'admin_user_id' => auth()->id(),
                'program_id' => $programId,
                'tier_id' => $tier->id,
                'tier_name' => $tier->name,
                'age_range' => $tier->age_min . '-' . $tier->age_max
            ]);

            return new ItemResponse(new MealPricingTierResource($tier), JsonResponse::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Failed to create pricing tier', [
                'admin_user_id' => auth()->id(),
                'program_id' => $programId,
                'tier_name' => $validatedData['name'] ?? null,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to create pricing tier.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified pricing tier.
     */
    public function update(MealPricingTierRequest $request, int $programId, int $tierId): ItemResponse|ErrorResponse
    {
        $validatedData = $request->validated();
        
        Log::info('Admin updating meal pricing tier', [
            'admin_user_id' => auth()->id(),
            'program_id' => $programId,
            'tier_id' => $tierId,
            'updated_fields' => array_keys($validatedData)
        ]);
        
        try {
            $tier = $this->tierRepository->find($tierId);

            if (!$tier || $tier->meal_program_id !== $programId) {
                Log::warning('Pricing tier not found for update', [
                    'admin_user_id' => auth()->id(),
                    'program_id' => $programId,
                    'tier_id' => $tierId
                ]);
                return new ErrorResponse('Pricing tier not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $data = $validatedData;
            $data['meal_program_id'] = $programId;
            $data['id'] = $tierId;
            
            $dto = MealPricingTierDTO::from($data);

            $tier = $this->upsertAction->execute($dto);

            Log::info('Meal pricing tier updated successfully', [
                'admin_user_id' => auth()->id(),
                'program_id' => $programId,
                'tier_id' => $tierId,
                'tier_name' => $tier->name,
                'updated_fields' => array_keys($validatedData)
            ]);

            return new ItemResponse(new MealPricingTierResource($tier));
        } catch (Exception $e) {
            Log::error('Failed to update pricing tier', [
                'admin_user_id' => auth()->id(),
                'program_id' => $programId,
                'tier_id' => $tierId,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to update pricing tier.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified pricing tier.
     */
    public function destroy(int $programId, int $tierId): EmptyResponse|ErrorResponse
    {
        Log::info('Admin deleting meal pricing tier', [
            'admin_user_id' => auth()->id(),
            'program_id' => $programId,
            'tier_id' => $tierId
        ]);
        
        try {
            $tier = $this->tierRepository->find($tierId);

            if (!$tier || $tier->meal_program_id !== $programId) {
                Log::warning('Pricing tier not found for deletion', [
                    'admin_user_id' => auth()->id(),
                    'program_id' => $programId,
                    'tier_id' => $tierId
                ]);
                return new ErrorResponse('Pricing tier not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $this->tierRepository->delete($tier);

            Log::info('Meal pricing tier deleted successfully', [
                'admin_user_id' => auth()->id(),
                'program_id' => $programId,
                'deleted_tier_id' => $tierId,
                'tier_name' => $tier->name
            ]);

            return new EmptyResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete pricing tier', [
                'admin_user_id' => auth()->id(),
                'program_id' => $programId,
                'tier_id' => $tierId,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to delete pricing tier.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
