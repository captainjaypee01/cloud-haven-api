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
        $filters = $request->only([
            'status', 'search', 'sort', 'per_page', 'page', 
            'date', 'date_from', 'date_to',
            'created_date', 'created_from', 'created_to'
        ]);
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
            // Check if the parameter is a numeric ID or a reference number
            if (is_numeric($booking)) {
                // It's a numeric ID
                $data = $this->bookingService->show((int)$booking);
            } else {
                // It's a reference number (contains letters/hyphens)
                $data = $this->bookingService->showByReferenceNumber($booking);
            }
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
        
        Log::info('Admin adding other charge to booking', [
            'admin_user_id' => auth()->id(),
            'booking_id' => $booking,
            'charge_amount' => $validated['amount'],
            'remarks' => $validated['remarks'] ?? null
        ]);
        
        try {
            $data = $this->bookingService->show($booking);
            $otherCharge = $data->otherCharges()->create($validated);
            
            Log::info('Other charge added successfully', [
                'admin_user_id' => auth()->id(),
                'booking_id' => $data->id,
                'booking_reference' => $data->reference_number,
                'charge_id' => $otherCharge->id,
                'charge_amount' => $validated['amount'],
                'remarks' => $validated['remarks'] ?? null
            ]);
            
            return new ItemResponse(new BookingResource($data), JsonResponse::HTTP_CREATED);
        } catch (ModelNotFoundException $e) {
            Log::warning('Booking not found for other charge', [
                'admin_user_id' => auth()->id(),
                'booking_id' => $booking
            ]);
            return new ErrorResponse('Booking not found.');
        }
        return new ItemResponse(new BookingResource($data->refresh()), JsonResponse::HTTP_CREATED);
    }

    public function reschedule(Request $request, $booking)
    {
        $bookingModel = $this->bookingService->show($booking);
        
        // Handle validation based on booking type
        if ($bookingModel->booking_type === 'day_tour') {
            // For Day Tour, only validate the tour date (same for check-in and check-out)
            $validated = $request->validate([
                'tour_date' => 'required|date|after_or_equal:today',
            ]);
            
            // Set both check-in and check-out to the same date for Day Tour
            $validated['check_in_date'] = $validated['tour_date'];
            $validated['check_out_date'] = $validated['tour_date'];
        } else {
            // For overnight bookings, validate check-in and check-out dates
            $validated = $request->validate([
                'check_in_date' => 'required|date|after_or_equal:today',
                'check_out_date' => 'required|date|after:check_in_date',
            ]);
        }
        
        // Calculate the original duration (in nights)
        $originalNights = Carbon::parse($bookingModel->check_in_date)->diffInDays(Carbon::parse($bookingModel->check_out_date));
        $newNights = Carbon::parse($validated['check_in_date'])->diffInDays(Carbon::parse($validated['check_out_date']));

        Log::info('Admin rescheduling booking', [
            'admin_user_id' => auth()->id(),
            'booking_id' => $bookingModel->id,
            'booking_reference' => $bookingModel->reference_number,
            'booking_type' => $bookingModel->booking_type,
            'original_check_in' => $bookingModel->check_in_date,
            'original_check_out' => $bookingModel->check_out_date,
            'new_check_in' => $validated['check_in_date'],
            'new_check_out' => $validated['check_out_date'],
            'original_nights' => $originalNights,
            'new_nights' => $newNights
        ]);

        // For overnight bookings, check that duration matches
        if ($bookingModel->booking_type !== 'day_tour' && $newNights !== $originalNights) {
            Log::warning('Reschedule rejected - duration mismatch', [
                'admin_user_id' => auth()->id(),
                'booking_id' => $bookingModel->id,
                'booking_reference' => $bookingModel->reference_number,
                'original_nights' => $originalNights,
                'new_nights' => $newNights
            ]);
            return new ErrorResponse("The reschedule duration must match the original booking duration ({$originalNights} night(s)).", 422);
        }

        // Proceed to update booking dates in a transaction
        DB::beginTransaction();
        try {
            $bookingModel->update([
                'check_in_date' => $validated['check_in_date'],
                'check_out_date' => $validated['check_out_date'],
            ]);
            DB::commit();
            
            Log::info('Booking rescheduled successfully', [
                'admin_user_id' => auth()->id(),
                'booking_id' => $bookingModel->id,
                'booking_reference' => $bookingModel->reference_number,
                'new_check_in' => $validated['check_in_date'],
                'new_check_out' => $validated['check_out_date'],
                'nights' => $newNights
            ]);
            
            return new ItemResponse(new BookingResource($bookingModel));
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to reschedule booking', [
                'admin_user_id' => auth()->id(),
                'booking_id' => $bookingModel->id,
                'booking_reference' => $bookingModel->reference_number,
                'error' => $e->getMessage()
            ]);
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
