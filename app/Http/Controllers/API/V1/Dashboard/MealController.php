<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Actions\ComputeMealQuoteAction;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\MealQuoteRequest;
use App\Http\Responses\ItemResponse;
use App\Http\Responses\ErrorResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class MealController extends Controller
{
    public function __construct(
        private MealCalendarServiceInterface $calendarService,
        private ComputeMealQuoteAction $computeMealQuoteAction
    ) {}

    /**
     * Get meal availability for a date range.
     */
    public function availability(Request $request): ItemResponse|ErrorResponse
    {
        try {
            $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after:from',
            ]);

            $startDate = Carbon::parse($request->from);
            $endDate = Carbon::parse($request->to);

            // Validate date range (e.g., max 365 days)
            if ($startDate->diffInDays($endDate) > 365) {
                return new ErrorResponse('Date range cannot exceed 365 days', JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $availability = $this->calendarService->getAvailabilityForDateRange($startDate, $endDate);

            return new ItemResponse(new \Illuminate\Http\Resources\Json\JsonResource($availability));
        } catch (Exception $e) {
            Log::error('Failed to get meal availability: ' . $e->getMessage());
            return new ErrorResponse('Unable to get meal availability.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get meal quote for a stay.
     */
    public function quote(MealQuoteRequest $request): ItemResponse|ErrorResponse
    {
        try {
            $validated = $request->validated();
            $quote = $this->computeMealQuoteAction->execute(
                $validated['check_in'],
                $validated['check_out'],
                $validated['adults'],
                $validated['children'] ?? 0
            );

            return new ItemResponse(new \Illuminate\Http\Resources\Json\JsonResource($quote->toArray()));
        } catch (\InvalidArgumentException $e) {
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Failed to compute meal quote: ' . $e->getMessage());
            return new ErrorResponse('Unable to compute meal quote.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
