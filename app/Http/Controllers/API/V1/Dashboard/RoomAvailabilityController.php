<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\RoomServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Room\BatchAvailabilityResource;
use App\Http\Responses\CollectionResponse;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RoomAvailabilityController extends Controller
{
    public function __construct(private RoomServiceInterface $roomService) {}

    public function batchCheck(Request $request): CollectionResponse|\Symfony\Component\HttpFoundation\JsonResponse
    {
        $request->validate([
            'check_in'  => 'required|date',
            'check_out'  => 'required|date|after:check_in',
        ]);
        
        $checkIn = $request->input('check_in');
        $checkOut = $request->input('check_out');
        
        // Validate 5-day maximum limit for overnight bookings
        $checkInDate = \Carbon\Carbon::parse($checkIn);
        $checkOutDate = \Carbon\Carbon::parse($checkOut);
        $daysDifference = $checkInDate->diffInDays($checkOutDate);
        
        if ($daysDifference > 5) {
            return response()->json([
                'error' => 'Overnight bookings are limited to a maximum of 5 days.'
            ], 422);
        }
        $items = $request->input('items', []);
        $response = [];
        $grouped = collect($items)->groupBy('room_id')->map->count();
        foreach ($grouped as $slug => $requested_count) {
            $room = $this->roomService->showBySlug($slug);
            $availability = $this->roomService->getDetailedAvailability($room->id, $checkIn, $checkOut);
            $canBook = $availability['available'] >= $requested_count;
            $response[] = [
                'room_id'           => $slug,
                'room_name'         => $room->name,
                'requested_count'   => $requested_count,
                'available'         => $canBook,
                'available_count'   => $availability['available'],
                'pending'           => $availability['pending'],
                'confirmed'         => $availability['confirmed'],
                'maintenance'       => $availability['maintenance'],
                'total_units'       => $availability['total_units'],
            ];
        }

        $response = new CollectionResponse(BatchAvailabilityResource::collection($response));
        
        // Add no-cache headers to prevent stale availability data
        $httpResponse = $response->toResponse($request);
        $httpResponse->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $httpResponse->headers->set('Pragma', 'no-cache');
        $httpResponse->headers->set('Expires', '0');
        return $httpResponse;
    }
}
