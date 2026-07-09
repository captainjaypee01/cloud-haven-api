<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\RoomPricingServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;

class RoomPricingCalendarController extends Controller
{
    public function __construct(
        private readonly RoomPricingServiceInterface $roomPricingService,
    ) {}

    public function index(Request $request, int $roomId)
    {
        try {
            $room = Room::findOrFail($roomId);
            $month = $request->validate([
                'month' => 'required|date_format:Y-m',
            ])['month'];

            $days = $this->roomPricingService->getCalendarMonth($roomId, $month);

            return new ItemResponse([
                'room' => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'slug' => $room->slug,
                    'default_price_per_night' => (float) $room->price_per_night,
                ],
                'month' => $month,
                'days' => $days,
            ]);
        } catch (ModelNotFoundException) {
            return new ErrorResponse('Room not found.', JsonResponse::HTTP_NOT_FOUND);
        }
    }

    public function preview(Request $request, int $roomId)
    {
        try {
            Room::findOrFail($roomId);

            $validated = $this->validateBulkPayload($request);

            $payload = $validated['mode'] === 'flat'
                ? (float) $validated['price']
                : $this->extractWeekdayPrices($validated);

            $preview = $this->roomPricingService->previewBulkChange(
                $roomId,
                $validated['from'],
                $validated['to'],
                $validated['mode'],
                $payload,
                $validated['skip_manual_overrides'] ?? true
            );

            return new ItemResponse($preview);
        } catch (ModelNotFoundException) {
            return new ErrorResponse('Room not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (InvalidArgumentException $e) {
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function update(Request $request, int $roomId)
    {
        try {
            Room::findOrFail($roomId);

            $validated = $request->validate([
                'mode' => 'required|in:single,flat,weekday_pattern',
            ]);

            if ($validated['mode'] === 'single') {
                return $this->updateSingleDay($request, $roomId);
            }

            $validated = array_merge($validated, $this->validateBulkPayload($request));

            if ($validated['mode'] === 'flat') {
                $result = $this->roomPricingService->bulkUpsertFlat(
                    $roomId,
                    $validated['from'],
                    $validated['to'],
                    (float) $validated['price'],
                    $validated['skip_manual_overrides'] ?? true
                );
            } else {
                $result = $this->roomPricingService->bulkUpsertWeekdayPattern(
                    $roomId,
                    $validated['from'],
                    $validated['to'],
                    $this->extractWeekdayPrices($validated),
                    $validated['skip_manual_overrides'] ?? true
                );
            }

            return new ItemResponse($result);
        } catch (ModelNotFoundException) {
            return new ErrorResponse('Room not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (InvalidArgumentException $e) {
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function destroy(int $roomId, string $date)
    {
        try {
            Room::findOrFail($roomId);
            app(\App\Contracts\Repositories\RoomDailyRateRepositoryInterface::class)
                ->deleteForRoomAndDate($roomId, Carbon::parse($date));

            return new EmptyResponse();
        } catch (ModelNotFoundException) {
            return new ErrorResponse('Room not found.', JsonResponse::HTTP_NOT_FOUND);
        }
    }

    public function revenueReport(Request $request)
    {
        $validated = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'room_id' => 'nullable|integer|exists:rooms,id',
        ]);

        $query = \App\Models\BookingRoomNightlyRate::query()
            ->with(['room:id,name,slug', 'booking:id,reference_number,status'])
            ->whereDate('date', '>=', $validated['from'])
            ->whereDate('date', '<=', $validated['to']);

        if (! empty($validated['room_id'])) {
            $query->where('room_id', $validated['room_id']);
        }

        $rows = $query->orderBy('date')->get()->map(fn ($row) => [
            'date' => $row->date->format('Y-m-d'),
            'day_of_week' => $row->date->format('l'),
            'room_id' => $row->room_id,
            'room_name' => $row->room?->name,
            'booking_reference' => $row->booking?->reference_number,
            'booking_status' => $row->booking?->status,
            'rate' => (float) $row->rate,
        ]);

        $summary = [
            'total_nights' => $rows->count(),
            'total_revenue' => round($rows->sum('rate'), 2),
            'by_room' => $rows->groupBy('room_id')->map(fn ($group) => [
                'room_name' => $group->first()['room_name'],
                'nights' => $group->count(),
                'revenue' => round($group->sum('rate'), 2),
            ])->values(),
        ];

        return new ItemResponse([
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }

    private function updateSingleDay(Request $request, int $roomId): ItemResponse|ErrorResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'price_per_night' => 'required|numeric|min:0',
            'is_manual_override' => 'boolean',
        ]);

        $rate = app(\App\Contracts\Repositories\RoomDailyRateRepositoryInterface::class)->upsert(
            $roomId,
            Carbon::parse($validated['date']),
            (float) $validated['price_per_night'],
            $validated['is_manual_override'] ?? true
        );

        return new ItemResponse([
            'date' => $rate->date->format('Y-m-d'),
            'price_per_night' => (float) $rate->price_per_night,
            'is_manual_override' => (bool) $rate->is_manual_override,
        ]);
    }

    private function validateBulkPayload(Request $request): array
    {
        $validated = $request->validate([
            'mode' => 'required|in:flat,weekday_pattern',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'skip_manual_overrides' => 'boolean',
            'price' => 'required_if:mode,flat|numeric|min:0',
            'mon' => 'nullable|numeric|min:0',
            'tue' => 'nullable|numeric|min:0',
            'wed' => 'nullable|numeric|min:0',
            'thu' => 'nullable|numeric|min:0',
            'fri' => 'nullable|numeric|min:0',
            'sat' => 'nullable|numeric|min:0',
            'sun' => 'nullable|numeric|min:0',
        ]);

        return $validated;
    }

    private function extractWeekdayPrices(array $validated): array
    {
        return [
            'mon' => $validated['mon'] ?? null,
            'tue' => $validated['tue'] ?? null,
            'wed' => $validated['wed'] ?? null,
            'thu' => $validated['thu'] ?? null,
            'fri' => $validated['fri'] ?? null,
            'sat' => $validated['sat'] ?? null,
            'sun' => $validated['sun'] ?? null,
        ];
    }
}
