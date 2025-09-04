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
        $request->validate([
            'check_in'  => 'required',
            'check_out'  => 'required',
        ]);
        $checkIn = $request->input('check_in');
        $checkOut = $request->input('check_out');
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

        return new CollectionResponse(BatchAvailabilityResource::collection($response));
    }
}
