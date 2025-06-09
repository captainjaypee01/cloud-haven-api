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
        try {
            $data = $this->amenityService->create($request->validated(), $request->user()->id);
        } catch (Exception $e) {
            Log::error($e->getMessage());
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
        try {
            $data = $this->amenityService->update($id, $request->validated());
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Amenity not found.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to update amenity.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new AmenityResource($data), JsonResponse::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): EmptyResponse|ErrorResponse
    {
        try {
            $this->amenityService->delete($id);
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Amenity not found.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to delete a room.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new EmptyResponse();
    }
}
