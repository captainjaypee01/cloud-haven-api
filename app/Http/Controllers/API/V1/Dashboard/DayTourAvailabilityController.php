<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\DayTourServiceInterface;
use App\Contracts\Services\RoomServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Room\BatchAvailabilityResource;
use App\Http\Responses\CollectionResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DayTourAvailabilityController extends Controller
{
    public function __construct(
        private DayTourServiceInterface $dayTourService,
        private RoomServiceInterface $roomService
    ) {}

    public function getAvailability(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);
        
        try {
            $date = Carbon::parse($request->input('date'));
            $availability = $this->dayTourService->getAvailabilityForDate($date);
            
            return new JsonResponse($availability->toArray());
            
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'date' => ['Unable to get availability for the selected date.']
            ]);
        }
    }

    /**
     * Batch check availability for multiple Day Tour rooms on a specific date
     * Similar to RoomAvailabilityController@batchCheck but for Day Tour
     */
    public function batchCheck(Request $request)
    {
        $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.room_id' => ['required', 'string'],
            'items.*.requested_count' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $date = $request->input('date');
            $items = $request->input('items', []);
            $response = [];

            // Group items by room_id and count total requested
            $grouped = collect($items)->groupBy('room_id')->map->sum('requested_count');

            foreach ($grouped as $roomSlug => $requestedCount) {
                try {
                    // Get room details
                    $room = $this->roomService->showBySlug($roomSlug);
                    
                    // Get detailed availability for this room on the specific date
                    $availability = $this->roomService->getDetailedAvailability($room->id, $date, $date);
                    
                    // Check if we can book the requested number of units
                    $canBook = $availability['available'] >= $requestedCount;
                    
                    $response[] = [
                        'room_id' => $roomSlug,
                        'room_name' => $room->name,
                        'requested_count' => $requestedCount,
                        'available' => $canBook,
                        'available_count' => $availability['available'],
                        'pending' => $availability['pending'],
                        'confirmed' => $availability['confirmed'],
                        'maintenance' => $availability['maintenance'],
                        'total_units' => $availability['total_units'],
                        'date' => $date,
                    ];
                } catch (\Exception $e) {
                    // If room not found or other error, mark as unavailable
                    $response[] = [
                        'room_id' => $roomSlug,
                        'room_name' => 'Unknown Room',
                        'requested_count' => $requestedCount,
                        'available' => false,
                        'available_count' => 0,
                        'pending' => 0,
                        'confirmed' => 0,
                        'maintenance' => 0,
                        'total_units' => 0,
                        'date' => $date,
                    ];
                }
            }

            return new CollectionResponse(BatchAvailabilityResource::collection($response));
            
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'date' => ['Unable to check availability for the selected date.']
            ]);
        }
    }
}