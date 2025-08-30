<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\DTO\RoomUnits\GenerateUnitsData;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomUnit;
use App\Services\RoomUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoomUnitController extends Controller
{
    public function __construct(
        private readonly RoomUnitService $roomUnitService
    ) {}

    /**
     * Display a listing of room units with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['room_id', 'status', 'search']);
        $sort = $request->get('sort');
        $perPage = min((int) $request->get('per_page', 15), 100);

        $roomUnits = $this->roomUnitService->getRoomUnits($filters, $sort, $perPage);

        return response()->json([
            'success' => true,
            'data' => $roomUnits,
        ]);
    }

    /**
     * Get room units for a specific room type.
     */
    public function getRoomUnits(Request $request, Room $room): JsonResponse
    {
        $filters = $request->only(['status', 'search']);
        $filters['room_id'] = $room->id;
        $sort = $request->get('sort');
        $perPage = min((int) $request->get('per_page', 15), 100);

        $roomUnits = $this->roomUnitService->getRoomUnits($filters, $sort, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'room' => $room,
                'units' => $roomUnits->items(),
                'pagination' => [
                    'current_page' => $roomUnits->currentPage(),
                    'per_page' => $roomUnits->perPage(),
                    'total' => $roomUnits->total(),
                    'last_page' => $roomUnits->lastPage(),
                ]
            ],
        ]);
    }

    /**
     * Get room availability statistics for a specific room type.
     */
    public function getRoomStats(Room $room): JsonResponse
    {
        $stats = $this->roomUnitService->getRoomAvailabilityStats($room->id);

        return response()->json([
            'success' => true,
            'data' => [
                'room' => $room,
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Generate room units in bulk.
     */
    public function generateUnits(Request $request, Room $room): JsonResponse
    {
        try {
            $data = GenerateUnitsData::from($request->all());
            $result = $this->roomUnitService->generateUnits($room, $data);

            return response()->json([
                'success' => true,
                'message' => "Generated {$result['total_created']} room units successfully" . 
                           ($result['total_skipped'] > 0 ? " ({$result['total_skipped']} skipped)" : ""),
                'data' => $result,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate room units: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified room unit.
     */
    public function show(RoomUnit $roomUnit): JsonResponse
    {
        $roomUnit->load('room');

        return response()->json([
            'success' => true,
            'data' => $roomUnit,
        ]);
    }

    /**
     * Update the specified room unit.
     */
    public function update(Request $request, RoomUnit $roomUnit): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'in:available,occupied,maintenance,blocked'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $updatedUnit = $this->roomUnitService->updateRoomUnit($roomUnit, $request->only(['status', 'notes']));

            return response()->json([
                'success' => true,
                'message' => 'Room unit updated successfully',
                'data' => $updatedUnit->load('room'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update room unit: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified room unit.
     */
    public function destroy(RoomUnit $roomUnit): JsonResponse
    {
        try {
            $this->roomUnitService->deleteRoomUnit($roomUnit);

            return response()->json([
                'success' => true,
                'message' => 'Room unit deleted successfully',
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete room unit: ' . $e->getMessage(),
            ], 500);
        }
    }
}
