<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomUnit;
use App\Models\RoomUnitBlockedDate;
use App\Services\RoomUnitBlockedDateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoomUnitBlockedDateController extends Controller
{
    public function __construct(
        private readonly RoomUnitBlockedDateService $roomUnitBlockedDateService
    ) {}

    /**
     * Display a listing of blocked dates for a specific room unit.
     */
    public function index(Request $request, RoomUnit $roomUnit): JsonResponse
    {
        $filters = $request->only(['active', 'search', 'date_from', 'date_to']);
        $sort = $request->get('sort');
        $perPage = min((int) $request->get('per_page', 15), 100);

        $blockedDates = $this->roomUnitBlockedDateService->getBlockedDatesForUnit(
            $roomUnit->id,
            $filters,
            $sort,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => [
                'room_unit' => $roomUnit->load('room'),
                'blocked_dates' => $blockedDates->items(),
                'pagination' => [
                    'current_page' => $blockedDates->currentPage(),
                    'per_page' => $blockedDates->perPage(),
                    'total' => $blockedDates->total(),
                    'last_page' => $blockedDates->lastPage(),
                ]
            ],
        ]);
    }

    /**
     * Display a listing of all blocked dates across all room units.
     */
    public function indexAll(Request $request): JsonResponse
    {
        $filters = $request->only(['room_unit_id', 'room_id', 'active', 'search', 'date_from', 'date_to']);
        $sort = $request->get('sort');
        $perPage = min((int) $request->get('per_page', 15), 100);

        $blockedDates = $this->roomUnitBlockedDateService->getAllBlockedDates(
            $filters,
            $sort,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => [
                'blocked_dates' => $blockedDates->items(),
                'pagination' => [
                    'current_page' => $blockedDates->currentPage(),
                    'per_page' => $blockedDates->perPage(),
                    'total' => $blockedDates->total(),
                    'last_page' => $blockedDates->lastPage(),
                ]
            ],
        ]);
    }

    /**
     * Store a newly created blocked date for a single room unit.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'room_unit_id' => ['required', 'exists:room_units,id'],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'expiry_date' => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:start_date'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $blockedDate = $this->roomUnitBlockedDateService->createBlockedDate($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Blocked date created successfully',
                'data' => $blockedDate->load('roomUnit.room'),
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create blocked date: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store blocked dates for multiple room units (bulk operation).
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $request->validate([
            'room_unit_ids' => ['required', 'array', 'min:1'],
            'room_unit_ids.*' => ['exists:room_units,id'],
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'expiry_date' => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:start_date'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $blockedDates = $this->roomUnitBlockedDateService->createBulkBlockedDates(
                $request->input('room_unit_ids'),
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => "Created {$blockedDates->count()} blocked dates successfully",
                'data' => $blockedDates->load('roomUnit.room'),
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create blocked dates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified blocked date.
     */
    public function show(RoomUnitBlockedDate $blockedDate): JsonResponse
    {
        $blockedDate->load('roomUnit.room');

        return response()->json([
            'success' => true,
            'data' => $blockedDate,
        ]);
    }

    /**
     * Update the specified blocked date.
     */
    public function update(Request $request, RoomUnitBlockedDate $blockedDate): JsonResponse
    {
        $request->validate([
            'start_date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'expiry_date' => ['sometimes', 'date', 'date_format:Y-m-d', 'before_or_equal:start_date'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $updatedBlockedDate = $this->roomUnitBlockedDateService->updateBlockedDate(
                $blockedDate,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Blocked date updated successfully',
                'data' => $updatedBlockedDate->load('roomUnit.room'),
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update blocked date: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle the active status of the specified blocked date.
     */
    public function toggleActive(Request $request, RoomUnitBlockedDate $blockedDate): JsonResponse
    {
        try {
            $updatedBlockedDate = $this->roomUnitBlockedDateService->toggleActiveStatus($blockedDate);

            return response()->json([
                'success' => true,
                'message' => 'Blocked date status updated successfully',
                'data' => $updatedBlockedDate->load('roomUnit.room'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle blocked date status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified blocked date.
     */
    public function destroy(RoomUnitBlockedDate $blockedDate): JsonResponse
    {
        try {
            $this->roomUnitBlockedDateService->deleteBlockedDate($blockedDate);

            return response()->json([
                'success' => true,
                'message' => 'Blocked date deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete blocked date: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statistics for blocked dates.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->roomUnitBlockedDateService->getBlockedDatesStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get blocked dates statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate all expired blocked dates.
     */
    public function deactivateExpired(): JsonResponse
    {
        try {
            $deactivatedCount = $this->roomUnitBlockedDateService->deactivateExpiredBlockedDates();

            return response()->json([
                'success' => true,
                'message' => "Deactivated {$deactivatedCount} expired blocked dates",
                'data' => ['deactivated_count' => $deactivatedCount],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate expired blocked dates: ' . $e->getMessage(),
            ], 500);
        }
    }
}