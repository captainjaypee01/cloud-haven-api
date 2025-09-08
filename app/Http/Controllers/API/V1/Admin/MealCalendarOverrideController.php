<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\UpsertMealCalendarOverrideAction;
use App\Contracts\Repositories\MealCalendarOverrideRepositoryInterface;
use App\DTO\MealOverrideDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MealCalendarOverrideRequest;
use App\Http\Resources\Admin\MealCalendarOverrideResource;
use App\Http\Responses\ItemResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\EmptyResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class MealCalendarOverrideController extends Controller
{
    public function __construct(
        private MealCalendarOverrideRepositoryInterface $overrideRepository,
        private UpsertMealCalendarOverrideAction $upsertAction
    ) {}

    /**
     * Store a newly created calendar override.
     */
    public function store(MealCalendarOverrideRequest $request, int $programId): ItemResponse|ErrorResponse
    {
        try {
            $validated = $request->validated();
            $dto = new MealOverrideDTO(
                id: null,
                mealProgramId: $programId,
                overrideType: $validated['override_type'],
                date: isset($validated['date']) ? Carbon::parse($validated['date']) : null,
                month: $validated['month'] ?? null,
                year: $validated['year'] ?? null,
                isActive: $validated['is_active'],
                note: $validated['note'] ?? null
            );

            $override = $this->upsertAction->execute($dto);

            return new ItemResponse(new MealCalendarOverrideResource($override), JsonResponse::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Failed to create calendar override: ' . $e->getMessage());
            return new ErrorResponse('Unable to create calendar override.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified calendar override.
     */
    public function update(MealCalendarOverrideRequest $request, int $programId, int $overrideId): ItemResponse|ErrorResponse
    {
        try {
            $override = $this->overrideRepository->find($overrideId);

            if (!$override || $override->meal_program_id !== $programId) {
                return new ErrorResponse('Calendar override not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $validated = $request->validated();
            $dto = new MealOverrideDTO(
                id: $overrideId,
                mealProgramId: $programId,
                overrideType: $validated['override_type'],
                date: isset($validated['date']) ? Carbon::parse($validated['date']) : null,
                month: $validated['month'] ?? null,
                year: $validated['year'] ?? null,
                isActive: $validated['is_active'],
                note: $validated['note'] ?? null
            );

            $override = $this->upsertAction->execute($dto);

            return new ItemResponse(new MealCalendarOverrideResource($override));
        } catch (Exception $e) {
            Log::error('Failed to update calendar override: ' . $e->getMessage());
            return new ErrorResponse('Unable to update calendar override.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified calendar override.
     */
    public function destroy(int $programId, int $overrideId): EmptyResponse|ErrorResponse
    {
        try {
            $override = $this->overrideRepository->find($overrideId);

            if (!$override || $override->meal_program_id !== $programId) {
                return new ErrorResponse('Calendar override not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $this->overrideRepository->delete($override);

            return new EmptyResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete calendar override: ' . $e->getMessage());
            return new ErrorResponse('Unable to delete calendar override.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
