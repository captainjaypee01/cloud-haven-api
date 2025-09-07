<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\DayTourPricing\CreateDayTourPricingAction;
use App\Actions\DayTourPricing\DeleteDayTourPricingAction;
use App\Actions\DayTourPricing\ToggleDayTourPricingStatusAction;
use App\Actions\DayTourPricing\UpdateDayTourPricingAction;
use App\Contracts\Services\DayTourPricingServiceInterface;
use App\DTO\DayTourPricing\NewDayTourPricingDTO;
use App\DTO\DayTourPricing\UpdateDayTourPricingDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\DayTourPricingResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class DayTourPricingController extends Controller
{
    public function __construct(
        private readonly DayTourPricingServiceInterface $dayTourPricingService,
        private readonly CreateDayTourPricingAction $createAction,
        private readonly UpdateDayTourPricingAction $updateAction,
        private readonly DeleteDayTourPricingAction $deleteAction,
        private readonly ToggleDayTourPricingStatusAction $toggleStatusAction
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['search', 'is_active', 'sort_by', 'sort_order', 'per_page']);
        
        // Convert sort parameters to the expected format
        if (isset($filters['sort_by']) && isset($filters['sort_order'])) {
            $filters['sort'] = $filters['sort_by'] . '|' . $filters['sort_order'];
        }
        
        $pricing = $this->dayTourPricingService->list($filters);

        return new CollectionResponse(
            DayTourPricingResource::collection($pricing),
            JsonResponse::HTTP_OK
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): ItemResponse|ErrorResponse
    {
        try {
            $dto = NewDayTourPricingDTO::from($request->all());
            $pricing = $this->createAction->execute($dto);
            
            Log::info('Day Tour Pricing created', [
                'id' => $pricing->id,
                'name' => $pricing->name,
                'price_per_pax' => $pricing->price_per_pax,
                'user_id' => $request->user()?->id
            ]);

            return new ItemResponse(new DayTourPricingResource($pricing));
        } catch (Exception $e) {
            Log::error('Failed to create Day Tour Pricing', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'user_id' => $request->user()?->id
            ]);

            return new ErrorResponse('Failed to create Day Tour Pricing. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): ItemResponse|ErrorResponse
    {
        try {
            $pricing = $this->dayTourPricingService->show($id);
            return new ItemResponse(new DayTourPricingResource($pricing));
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Day Tour Pricing not found.');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): ItemResponse|ErrorResponse
    {
        try {
            $dto = UpdateDayTourPricingDTO::from($request->all());
            $pricing = $this->updateAction->execute($id, $dto);
            
            Log::info('Day Tour Pricing updated', [
                'id' => $pricing->id,
                'name' => $pricing->name,
                'price_per_pax' => $pricing->price_per_pax,
                'user_id' => $request->user()?->id
            ]);

            return new ItemResponse(new DayTourPricingResource($pricing));
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Day Tour Pricing not found.');
        } catch (Exception $e) {
            Log::error('Failed to update Day Tour Pricing', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'user_id' => $request->user()?->id
            ]);

            return new ErrorResponse('Failed to update Day Tour Pricing. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): EmptyResponse|ErrorResponse
    {
        try {
            $pricing = $this->dayTourPricingService->show($id);
            $this->deleteAction->execute($id);
            
            Log::info('Day Tour Pricing deleted', [
                'id' => $pricing->id,
                'name' => $pricing->name,
                'user_id' => request()->user()?->id
            ]);

            return new EmptyResponse();
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Day Tour Pricing not found.');
        } catch (Exception $e) {
            Log::error('Failed to delete Day Tour Pricing', [
                'id' => $id,
                'error' => $e->getMessage(),
                'user_id' => request()->user()?->id
            ]);

            return new ErrorResponse('Failed to delete Day Tour Pricing. Please try again.');
        }
    }

    /**
     * Toggle the active status of the pricing.
     */
    public function toggleStatus(int $id): ItemResponse|ErrorResponse
    {
        try {
            $pricing = $this->toggleStatusAction->execute($id);
            
            Log::info('Day Tour Pricing status toggled', [
                'id' => $pricing->id,
                'is_active' => $pricing->is_active,
                'user_id' => request()->user()?->id
            ]);

            return new ItemResponse(new DayTourPricingResource($pricing));
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Day Tour Pricing not found.');
        } catch (Exception $e) {
            Log::error('Failed to toggle Day Tour Pricing status', [
                'id' => $id,
                'error' => $e->getMessage(),
                'user_id' => request()->user()?->id
            ]);

            return new ErrorResponse('Failed to update pricing status. Please try again.');
        }
    }

}