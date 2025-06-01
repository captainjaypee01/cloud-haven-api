<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\RoomServiceInterface;
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

    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->roomService->list($filters);
        return new CollectionResponse(new RoomCollection($paginator), JsonResponse::HTTP_OK);
    }

    public function show($room): ItemResponse|ErrorResponse
    {
        try {
            $data = $this->roomService->show($room);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Room not found.');
        }
        return new ItemResponse(new RoomResource($data));
    }

    public function store(StoreRoomRequest $request): ItemResponse|ErrorResponse
    {
        try {
            $data = $this->roomService->create($request->validated(), $request->user()->id);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to create a room.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new RoomResource($data), JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateRoomRequest $request, $room): ItemResponse|ErrorResponse
    {
        try {
            $data = $this->roomService->update($request->validated(), $room, $request->user()->id);
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Room not found.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to update a room.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new RoomResource($data), JsonResponse::HTTP_OK);
    }

    public function destroy(Request $request, $room): EmptyResponse|ErrorResponse
    {
        try {
            $this->roomService->delete($room, $request->user()->id);
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Room not found.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to delete a room.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new EmptyResponse();
    }
}
