<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\RoomServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Http\Resources\RoomCollection;
use App\Http\Resources\RoomResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class RoomController extends Controller
{
    public function __construct(
        private readonly RoomServiceInterface $roomService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page', 'room_type']);
        $paginator = $this->roomService->list($filters);
        return new CollectionResponse(new RoomCollection($paginator), JsonResponse::HTTP_OK);
    }

    /**
     * Display the specified resource.
     */
    public function show($room): ItemResponse|ErrorResponse
    {
        try {
            $data = $this->roomService->show($room);
            $data->load(['images', 'amenities']);  // include images in result
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Room not found.');
        }
        return new ItemResponse(new RoomResource($data));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoomRequest $request): ItemResponse|ErrorResponse
    {
        $validatedData = $request->validated();
        
        Log::info('Admin creating new room', [
            'admin_user_id' => $request->user()->id,
            'room_name' => $validatedData['name'] ?? null,
            'room_type' => $validatedData['type'] ?? null
        ]);
        
        try {
            $data = $this->roomService->create($validatedData, $request->user()->id);
            
            Log::info('Room created successfully', [
                'admin_user_id' => $request->user()->id,
                'room_id' => $data->id,
                'room_name' => $data->name,
                'room_type' => $data->type
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to create room', [
                'admin_user_id' => $request->user()->id,
                'room_name' => $validatedData['name'] ?? null,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to create a room.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new RoomResource($data), JsonResponse::HTTP_CREATED);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoomRequest $request, $room): ItemResponse|ErrorResponse
    {
        $validatedData = $request->validated();
        
        Log::info('Admin updating room', [
            'admin_user_id' => $request->user()->id,
            'room_id' => $room,
            'updated_fields' => array_keys($validatedData)
        ]);
        
        try {
            $data = $this->roomService->update($validatedData, $room, $request->user()->id);
            
            Log::info('Room updated successfully', [
                'admin_user_id' => $request->user()->id,
                'room_id' => $room,
                'room_name' => $data->name,
                'updated_fields' => array_keys($validatedData)
            ]);
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Room not found for update', [
                'admin_user_id' => $request->user()->id,
                'room_id' => $room
            ]);
            return new ErrorResponse('Room not found.');
        } catch (Exception $e) {
            Log::error('Failed to update room', [
                'admin_user_id' => $request->user()->id,
                'room_id' => $room,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to update a room.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new RoomResource($data), JsonResponse::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $room): EmptyResponse|ErrorResponse
    {
        Log::info('Admin deleting room', [
            'admin_user_id' => $request->user()->id,
            'room_id' => $room
        ]);
        
        try {
            $this->roomService->delete($room, $request->user()->id);
            
            Log::info('Room deleted successfully', [
                'admin_user_id' => $request->user()->id,
                'deleted_room_id' => $room
            ]);
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Room not found for deletion', [
                'admin_user_id' => $request->user()->id,
                'room_id' => $room
            ]);
            return new ErrorResponse('Room not found.');
        } catch (Exception $e) {
            Log::error('Failed to delete room', [
                'admin_user_id' => $request->user()->id,
                'room_id' => $room,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to delete a room.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new EmptyResponse();
    }
}
