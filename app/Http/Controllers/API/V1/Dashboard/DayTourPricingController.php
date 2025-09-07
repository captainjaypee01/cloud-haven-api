<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\DayTourPricingServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\DayTourPricingResource;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DayTourPricingController extends Controller
{
    public function __construct(
        private readonly DayTourPricingServiceInterface $dayTourPricingService
    ) {}

    /**
     * Get current active pricing for a specific date (public endpoint)
     */
    public function getCurrentPricing(Request $request): ItemResponse|ErrorResponse
    {
        try {
            $date = $request->query('date', now()->format('Y-m-d'));
            $pricing = $this->dayTourPricingService->getActivePricingForDate($date);
            
            if (!$pricing) {
                return new ErrorResponse('No active pricing found for the selected date.');
            }
            
            return new ItemResponse(new DayTourPricingResource($pricing));
        } catch (Exception $e) {
            Log::error('Failed to get current Day Tour Pricing', [
                'date' => $request->query('date'),
                'error' => $e->getMessage()
            ]);

            return new ErrorResponse('Failed to get current pricing. Please try again.');
        }
    }
}