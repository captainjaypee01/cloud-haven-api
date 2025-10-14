<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\RoomServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Room\PublicRoomCollection;
use App\Http\Resources\Room\PublicRoomResource;
use App\Http\Resources\Room\RoomAvailabilityResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Carbon\Carbon;
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
    public function index(Request $request): CollectionResponse|\Symfony\Component\HttpFoundation\JsonResponse
    {
        $checkIn = $request->query('check_in');
        $checkOut = $request->query('check_out');
        if ($checkIn && $checkOut) {
            $rooms = $this->roomService->listRoomsWithAvailability($checkIn, $checkOut);
            $response = new CollectionResponse(new PublicRoomCollection($rooms), JsonResponse::HTTP_OK);
            
            // Add no-cache headers when availability data is included
            $httpResponse = $response->toResponse($request);
            $httpResponse->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $httpResponse->headers->set('Pragma', 'no-cache');
            $httpResponse->headers->set('Expires', '0');
            return $httpResponse;
        }
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page', 'room_type']);
        $paginator = $this->roomService->listPublicRooms($filters);
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
        $rooms = $this->roomService->listFeaturedRooms();
        return new CollectionResponse(new PublicRoomCollection($rooms), JsonResponse::HTTP_OK);
    }

    /**
     * Check availability for a specific room.
     */
    public function checkAvailability(Request $request, $roomSlug): ItemResponse|ErrorResponse|\Symfony\Component\HttpFoundation\JsonResponse
    {
        $request->validate([
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
        ]);

        // Validate 5-day maximum limit for overnight bookings
        $checkInDate = \Carbon\Carbon::parse($request->input('check_in'));
        $checkOutDate = \Carbon\Carbon::parse($request->input('check_out'));
        $daysDifference = $checkInDate->diffInDays($checkOutDate);
        
        if ($daysDifference > 5) {
            return new ErrorResponse('Overnight bookings are limited to a maximum of 5 days.', 422);
        }

        try {
            $room = $this->roomService->showBySlug($roomSlug);
            $availability = $this->roomService->getDetailedAvailability(
                $room->id,
                $request->input('check_in'),
                $request->input('check_out')
            );

            $response = new ItemResponse(new RoomAvailabilityResource([
                'room_type_id' => $room->slug,
                'room_name' => $room->name,
                'available_units' => $availability['available'],
                'pending' => $availability['pending'],
                'confirmed' => $availability['confirmed'],
                'maintenance' => $availability['maintenance'],
                'total_units' => $availability['total_units'],
                'check_in' => $request->input('check_in'),
                'check_out' => $request->input('check_out'),
            ]));

            // Add no-cache headers to prevent stale availability data
            $httpResponse = $response->toResponse($request);
            $httpResponse->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $httpResponse->headers->set('Pragma', 'no-cache');
            $httpResponse->headers->set('Expires', '0');
            return $httpResponse;
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Room not found.');
        }
    }
}
