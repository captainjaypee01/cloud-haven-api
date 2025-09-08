<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\AmenityServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Amenity\StoreAmenityRequest;
use App\Http\Requests\Amenity\UpdateAmenityRequest;
use App\Http\Resources\Amenity\AmenityCollection;
use App\Http\Resources\Amenity\AmenityResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Utils\ChangeLogger;

class AmenityController extends Controller
{
    public function __construct(
        private readonly AmenityServiceInterface $amenityService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->amenityService->list($filters);
        return new CollectionResponse(new AmenityCollection($paginator), JsonResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAmenityRequest $request): ItemResponse|ErrorResponse
    {
        $validatedData = $request->validated();
        
        Log::info('Admin creating new amenity', [
            'admin_user_id' => $request->user()->id,
            'amenity_name' => $validatedData['name'] ?? null,
            'amenity_type' => $validatedData['type'] ?? null
        ]);
        
        try {
            $data = $this->amenityService->create($validatedData, $request->user()->id);
            
            Log::info('Amenity created successfully', [
                'admin_user_id' => $request->user()->id,
                'amenity_id' => $data->id,
                'amenity_name' => $data->name,
                'amenity_type' => $data->type
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to create amenity', [
                'admin_user_id' => $request->user()->id,
                'amenity_name' => $validatedData['name'] ?? null,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to create amenity.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new AmenityResource($data), JsonResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): ItemResponse|ErrorResponse
    {
        try {
            $data = $this->amenityService->show($id);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Amenity not found.');
        }
        return new ItemResponse(new AmenityResource($data));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAmenityRequest $request, int $id): ItemResponse|ErrorResponse
    {
        $validatedData = $request->validated();
        
        try {
            // Get original amenity data before update
            $originalAmenity = $this->amenityService->show($id);
            $originalValues = $originalAmenity->only(array_keys($validatedData));
            
            ChangeLogger::logUpdateAttempt(
                'Admin updating amenity',
                $originalValues,
                $validatedData,
                [
                    'admin_user_id' => auth()->id(),
                    'amenity_id' => $id,
                    'amenity_name' => $originalAmenity->name
                ]
            );
            
            $data = $this->amenityService->update($id, $validatedData);
            
            ChangeLogger::logSuccessfulUpdate(
                'Amenity updated successfully',
                $originalValues,
                $validatedData,
                [
                    'admin_user_id' => auth()->id(),
                    'amenity_id' => $id,
                    'amenity_name' => $data->name
                ]
            );
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Amenity not found for update', [
                'admin_user_id' => auth()->id(),
                'amenity_id' => $id
            ]);
            return new ErrorResponse('Amenity not found.');
        } catch (Exception $e) {
            Log::error('Failed to update amenity', [
                'admin_user_id' => auth()->id(),
                'amenity_id' => $id,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to update amenity.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new AmenityResource($data), JsonResponse::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): EmptyResponse|ErrorResponse
    {
        Log::info('Admin deleting amenity', [
            'admin_user_id' => auth()->id(),
            'amenity_id' => $id
        ]);
        
        try {
            $this->amenityService->delete($id);
            
            Log::info('Amenity deleted successfully', [
                'admin_user_id' => auth()->id(),
                'deleted_amenity_id' => $id
            ]);
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Amenity not found for deletion', [
                'admin_user_id' => auth()->id(),
                'amenity_id' => $id
            ]);
            return new ErrorResponse('Amenity not found.');
        } catch (Exception $e) {
            Log::error('Failed to delete amenity', [
                'admin_user_id' => auth()->id(),
                'amenity_id' => $id,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to delete amenity.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new EmptyResponse();
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request, int $id): ItemResponse|ErrorResponse
    {
        $validated = $request->validate([
            'status'    => 'required|string'
        ]);
        
        Log::info('Admin updating amenity status', [
            'admin_user_id' => auth()->id(),
            'amenity_id' => $id,
            'new_status' => $validated['status']
        ]);
        
        try {
            $data = $this->amenityService->updateStatus($id, $validated['status']);
            
            Log::info('Amenity status updated successfully', [
                'admin_user_id' => auth()->id(),
                'amenity_id' => $id,
                'amenity_name' => $data->name,
                'new_status' => $validated['status']
            ]);
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Amenity not found for status update', [
                'admin_user_id' => auth()->id(),
                'amenity_id' => $id
            ]);
            return new ErrorResponse('Amenity not found.');
        } catch (Exception $e) {
            Log::error('Failed to update amenity status', [
                'admin_user_id' => auth()->id(),
                'amenity_id' => $id,
                'new_status' => $validated['status'],
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to update amenity.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new AmenityResource($data), JsonResponse::HTTP_OK);
    }
}
