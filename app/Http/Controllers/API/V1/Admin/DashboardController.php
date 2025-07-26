<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Contracts\Services\DashboardServiceInterface;
use App\Http\Responses\ItemResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboardService
    ) {}

    public function index(Request $request)
    {
        // Get all dashboard info (metrics, charts, etc.)
        $data = $this->dashboardService->getDashboardData();

        // Return as JSON response (wrap in ItemResponse for consistent format)
        return new ItemResponse(new JsonResource($data));
        // Alternatively: return response()->json(['data' => $data]);
    }
}
