<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Actions\Bookings\ComputeOvernightQuoteAction;
use App\Http\Controllers\Controller;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;

class OvernightQuoteController extends Controller
{
    public function __construct(
        private readonly ComputeOvernightQuoteAction $computeQuoteAction,
    ) {}

    public function quote(Request $request)
    {
        $validated = $request->validate([
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'rooms' => 'required|array|min:1',
            'rooms.*.room_id' => 'required|string',
            'rooms.*.adults' => 'nullable|integer|min:0',
            'rooms.*.children' => 'nullable|integer|min:0',
        ]);

        try {
            $quote = $this->computeQuoteAction->execute(
                $validated['check_in_date'],
                $validated['check_out_date'],
                $validated['rooms']
            );

            return new ItemResponse($quote->toArray());
        } catch (InvalidArgumentException $e) {
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
