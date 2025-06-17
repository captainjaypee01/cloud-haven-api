<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\RoomServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Room\PublicRoomCollection;
use App\Http\Resources\Room\PublicRoomResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
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
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->roomService->listPublicRooms($filters);
        // sleep(2);
        return new CollectionResponse(new PublicRoomCollection($paginator), JsonResponse::HTTP_OK);
    }

    /**
     * Display the specified resource.
     */
    public function show($room): ItemResponse|ErrorResponse
    {
        try {
            $data = $this->roomService->showBySlug($room);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Room not found.');
        }
        return new ItemResponse(new PublicRoomResource($data));
    }
    
    /**
     * Display the featured rooms.
     */
    public function featuredRooms(Request $request): CollectionResponse
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->roomService->listPublicRooms($filters);
        // sleep(2);
        return new CollectionResponse(new PublicRoomCollection($paginator), JsonResponse::HTTP_OK);
    }
}
