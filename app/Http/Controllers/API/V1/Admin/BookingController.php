<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\BookingServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Booking\BookingCollection;
use App\Http\Resources\Booking\BookingResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingServiceInterface $bookingService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->bookingService->list($filters);
        return new CollectionResponse(new BookingCollection($paginator), JsonResponse::HTTP_OK);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show($booking): ItemResponse|ErrorResponse
    {
        try {
            $data = $this->bookingService->show($booking);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Booking not found.');
        }
        return new ItemResponse(new BookingResource($data));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function storeOtherCharge(Request $request, $booking)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string',
        ]);
        try {
            $data = $this->bookingService->show($booking);
            $data->otherCharges()->create($validated);
            return new ItemResponse(new BookingResource($data), JsonResponse::HTTP_CREATED);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Booking not found.');
        }
        return new ItemResponse(new BookingResource($data->refresh()), JsonResponse::HTTP_CREATED);
    }

    public function reschedule(Request $request, $booking)
    {
        $validated = $request->validate([
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        $booking = $this->bookingService->show($booking);
        // Calculate the original duration (in nights)
        $originalNights = Carbon::parse($booking->check_in_date)->diffInDays(Carbon::parse($booking->check_out_date));
        $newNights = Carbon::parse($validated['check_in_date'])->diffInDays(Carbon::parse($validated['check_out_date']));

        if ($newNights !== $originalNights) {
            return new ErrorResponse("The reschedule duration must match the original booking duration ({$originalNights} night(s)).", 422);
        }

        // Proceed to update booking dates in a transaction
        DB::beginTransaction();
        try {
            $booking->update([
                'check_in_date' => $validated['check_in_date'],
                'check_out_date' => $validated['check_out_date'],
            ]);
            DB::commit();
            return new ItemResponse(new BookingResource($booking));
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return new ErrorResponse("Unable to reschedule", JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calendar data endpoint for admin.
     * GET /v1/admin/bookings/calendar?start=YYYY-MM-DD&end=YYYY-MM-DD[&status=paid,downpayment][&room_type_id=ID]
     */
    public function calendar(Request $request)
    {
        $validated = $request->validate([
            'start' => 'required|date_format:Y-m-d',
            'end' => 'required|date_format:Y-m-d',
            'status' => 'sometimes|string',
            'room_type_id' => 'sometimes|integer',
        ]);

        try {
            $data = $this->bookingService->getCalendar($validated);
        } catch (\InvalidArgumentException $e) {
            return new ErrorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Calendar endpoint error: ' . $e->getMessage());
            return new ErrorResponse('Unable to get calendar data', 500);
        }

        return response()->json($data);
    }
}
