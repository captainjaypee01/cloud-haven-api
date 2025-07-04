<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\RoomServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Room\BatchAvailabilityResource;
use App\Http\Responses\CollectionResponse;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomAvailabilityController extends Controller
{
    public function __construct(private RoomServiceInterface $roomService) {}

    public function batchCheck(Request $request)
    {
        $checkIn = $request->input('check_in');
        $checkOut = $request->input('check_out');
        $items = $request->input('items', []);
        $response = [];
        $grouped = collect($items)->groupBy('room_id')->map->count();

        foreach ($items as $item) {
            $countForThisRoom = $grouped[$item['room_id']];
            $room = Room::where('slug', $item['room_id'])->first();

            // $room = $this->roomService->showBySlug($item['room_id']); // Returns Room model
            $available = $this->roomService->availableUnits($room->id, $checkIn, $checkOut);
            $canBook = $available >= $countForThisRoom;
            $response[] = [
                'room_id'           => $item['room_id'],
                'requested_count'   => 1, // or $countForThisRoom
                'available'         => $canBook,
                'available_count'   => $available,
            ];
        }

        return new CollectionResponse(BatchAvailabilityResource::collection($response));
    }
}
