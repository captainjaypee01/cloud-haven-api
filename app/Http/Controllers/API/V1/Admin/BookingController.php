<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\BookingServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\Booking\BookingCollection;
use App\Http\Resources\Booking\BookingResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use App\Services\RoomUnitService;
use App\Services\EmailTrackingService;
use App\DTO\Bookings\BookingData;
use App\Http\Requests\Booking\WalkInBookingRequest;
use App\Services\Bookings\WalkInBookingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingServiceInterface $bookingService,
        private readonly RoomUnitService $roomUnitService,
        private readonly WalkInBookingService $walkInBookingService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'status', 'search', 'sort', 'per_page', 'page', 
            'date', 'date_from', 'date_to',
            'created_date', 'created_from', 'created_to',
            'booking_type', 'booking_source'
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
     * Create a walk-in booking for today only.
     */
    public function storeWalkIn(WalkInBookingRequest $request)
    {
        $validated = $request->validated();
        $bookingData = BookingData::from($validated);

        Log::info('Admin creating walk-in booking', [
            'admin_user_id' => Auth::id(),
            'guest_name' => $bookingData->guest_name,
            'guest_email' => $bookingData->guest_email,
            'booking_type' => $bookingData->booking_type,
            'check_in_date' => $bookingData->check_in_date,
            'check_out_date' => $bookingData->check_out_date,
            'total_adults' => $bookingData->total_adults,
            'total_children' => $bookingData->total_children,
            'room_count' => count($bookingData->rooms)
        ]);
        
        try {
            // Use the new WalkInBookingService which handles both overnight and day tour bookings
            $booking = $this->walkInBookingService->createWalkInBooking($bookingData);
            
            Log::info('Walk-in booking created successfully', [
                'admin_user_id' => Auth::id(),
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'guest_name' => $booking->guest_name,
                'booking_type' => $booking->booking_type,
                'check_in_date' => $booking->check_in_date,
                'check_out_date' => $booking->check_out_date,
                'final_price' => $booking->final_price
            ]);

            return new ItemResponse(new BookingResource($booking), JsonResponse::HTTP_CREATED);
        } catch (\App\Exceptions\RoomNotAvailableException $e) {
            Log::warning('Walk-in booking failed - room not available', [
                'admin_user_id' => Auth::id(),
                'guest_name' => $bookingData->guest_name,
                'booking_type' => $bookingData->booking_type,
                'check_in_date' => $bookingData->check_in_date,
                'check_out_date' => $bookingData->check_out_date,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_CONFLICT);
        } catch (\Exception $e) {
            Log::error('Walk-in booking creation failed', [
                'admin_user_id' => Auth::id(),
                'guest_name' => $bookingData->guest_name,
                'booking_type' => $bookingData->booking_type,
                'check_in_date' => $bookingData->check_in_date,
                'check_out_date' => $bookingData->check_out_date,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to create walk-in booking. Please try again.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
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
            'admin_user_id' => Auth::user()->id,
            'booking_id' => $booking,
            'charge_amount' => $validated['amount'],
            'remarks' => $validated['remarks'] ?? null
        ]);
        
        try {
            $data = $this->bookingService->show($booking);
            $otherCharge = $data->otherCharges()->create($validated);
            
            Log::info('Other charge added successfully', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $data->id,
                'booking_reference' => $data->reference_number,
                'charge_id' => $otherCharge->id,
                'charge_amount' => $validated['amount'],
                'remarks' => $validated['remarks'] ?? null
            ]);
            
            return new ItemResponse(new BookingResource($data), JsonResponse::HTTP_CREATED);
        } catch (ModelNotFoundException $e) {
            Log::warning('Booking not found for other charge', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $booking
            ]);
            return new ErrorResponse('Booking not found.');
        }
        return new ItemResponse(new BookingResource($data->refresh()), JsonResponse::HTTP_CREATED);
    }

    public function reschedule(Request $request, $booking)
    {
        $bookingModel = $this->bookingService->show($booking);
        
        // Calculate 30-day limit from original check-in date
        $originalCheckIn = Carbon::parse($bookingModel->check_in_date);
        $maxRescheduleDate = $originalCheckIn->copy()->addDays(30);
        
        // Handle validation based on booking type
        if ($bookingModel->booking_type === 'day_tour') {
            // For Day Tour, only validate the tour date (same for check-in and check-out)
            $validated = $request->validate([
                'tour_date' => [
                    'required',
                    'date',
                    'after_or_equal:today',
                    'before_or_equal:' . $maxRescheduleDate->toDateString()
                ],
            ]);
            
            // Set both check-in and check-out to the same date for Day Tour
            $validated['check_in_date'] = $validated['tour_date'];
            $validated['check_out_date'] = $validated['tour_date'];
        } else {
            // For overnight bookings, validate check-in and check-out dates
            $validated = $request->validate([
                'check_in_date' => [
                    'required',
                    'date',
                    'after_or_equal:today',
                    'before_or_equal:' . $maxRescheduleDate->toDateString()
                ],
                'check_out_date' => 'required|date|after:check_in_date',
            ]);
        }
        
        // Calculate the original duration (in nights)
        $originalNights = Carbon::parse($bookingModel->check_in_date)->diffInDays(Carbon::parse($bookingModel->check_out_date));
        $newNights = Carbon::parse($validated['check_in_date'])->diffInDays(Carbon::parse($validated['check_out_date']));

        Log::info('Admin rescheduling booking', [
            'admin_user_id' => Auth::user()->id,
            'booking_id' => $bookingModel->id,
            'booking_reference' => $bookingModel->reference_number,
            'booking_type' => $bookingModel->booking_type,
            'original_check_in' => $bookingModel->check_in_date,
            'original_check_out' => $bookingModel->check_out_date,
            'new_check_in' => $validated['check_in_date'],
            'new_check_out' => $validated['check_out_date'],
            'original_nights' => $originalNights,
            'new_nights' => $newNights,
            'max_reschedule_date' => $maxRescheduleDate->toDateString()
        ]);

        // For overnight bookings, check that duration matches
        if ($bookingModel->booking_type !== 'day_tour' && $newNights !== $originalNights) {
            Log::warning('Reschedule rejected - duration mismatch', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $bookingModel->id,
                'booking_reference' => $bookingModel->reference_number,
                'original_nights' => $originalNights,
                'new_nights' => $newNights
            ]);
            return new ErrorResponse("The reschedule duration must match the original booking duration ({$originalNights} night(s)).", 422);
        }

        // Check room availability for the new dates
        try {
            $checkAvailabilityAction = app(\App\Actions\Bookings\CheckRoomAvailabilityAction::class);
            
            // Load booking rooms with room relationship to get the slug
            $bookingModel->load('bookingRooms.room');
            
            $bookingRoomArr = $bookingModel->bookingRooms->map(function ($br) {
                return (object) [
                    'room_id' => $br->room->slug, // Use room slug, not numeric ID
                    'quantity' => 1,
                    'adults' => $br->adults,
                    'children' => $br->children,
                    'total_guests' => $br->total_guests,
                ];
            })->toArray();
            
            $checkAvailabilityAction->execute(
                $bookingRoomArr,
                $validated['check_in_date'],
                $validated['check_out_date']
            );
        } catch (\App\Exceptions\RoomNotAvailableException $e) {
            Log::warning('Reschedule rejected - room not available', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $bookingModel->id,
                'booking_reference' => $bookingModel->reference_number,
                'new_check_in' => $validated['check_in_date'],
                'new_check_out' => $validated['check_out_date'],
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse("One or more rooms are not available for the selected dates. Please choose different dates.", 422);
        }

        // Proceed to update booking dates in a transaction
        DB::beginTransaction();
        try {
            $oldCheckIn = $bookingModel->check_in_date;
            $oldCheckOut = $bookingModel->check_out_date;
            
            $bookingModel->update([
                'check_in_date' => $validated['check_in_date'],
                'check_out_date' => $validated['check_out_date'],
            ]);
            
            // Send reschedule email notification
            try {
                EmailTrackingService::sendWithTracking(
                    $bookingModel->guest_email,
                    new \App\Mail\BookingReschedule($bookingModel, $oldCheckIn, $oldCheckOut),
                    'booking_reschedule',
                    [
                        'booking_id' => $bookingModel->id,
                        'reference_number' => $bookingModel->reference_number,
                        'guest_name' => $bookingModel->guest_name,
                        'old_check_in' => $oldCheckIn,
                        'old_check_out' => $oldCheckOut,
                        'new_check_in' => $validated['check_in_date'],
                        'new_check_out' => $validated['check_out_date']
                    ]
                );
            } catch (\Exception $emailError) {
                Log::warning('Failed to send reschedule email', [
                    'booking_id' => $bookingModel->id,
                    'reference_number' => $bookingModel->reference_number,
                    'error' => $emailError->getMessage()
                ]);
                // Don't fail the reschedule if email fails
            }
            
            DB::commit();
            
            Log::info('Booking rescheduled successfully', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $bookingModel->id,
                'booking_reference' => $bookingModel->reference_number,
                'old_check_in' => $oldCheckIn,
                'old_check_out' => $oldCheckOut,
                'new_check_in' => $validated['check_in_date'],
                'new_check_out' => $validated['check_out_date'],
                'nights' => $newNights
            ]);
            
            return new ItemResponse(new BookingResource($bookingModel));
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to reschedule booking', [
                'admin_user_id' => Auth::user()->id,
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

    /**
     * Get available room units for a specific room type and date range.
     * Used for room unit assignment in admin booking details.
     */
    public function getAvailableRoomUnits(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after_or_equal:check_in_date',
        ]);

        try {
            $booking = $this->bookingService->show($bookingId);
            
            // Get available units for the room and date range using the proper availability logic
            $availableUnits = $this->roomUnitService->getAvailableUnitsForReassignment(
                $validated['room_id'],
                $validated['check_in_date'],
                $validated['check_out_date']
            )
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'status' => $unit->status->value,
                    'notes' => $unit->notes,
                ];
            })
            ->values();

            return response()->json([
                'available_units' => $availableUnits,
                'room_id' => $validated['room_id'],
                'check_in_date' => $validated['check_in_date'],
                'check_out_date' => $validated['check_out_date'],
            ]);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Booking not found.');
        } catch (\Exception $e) {
            Log::error('Failed to get available room units', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to get available room units', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update PWD/Senior discount for a booking.
     */
    public function updatePwdSeniorDiscount(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'pwd_senior_discount' => 'required|numeric|min:0',
            'discount_reason' => 'required|string|max:500',
        ]);

        try {
            $booking = $this->bookingService->show($bookingId);

            DB::beginTransaction();

            // Update the PWD/Senior discount
            $booking->update([
                'pwd_senior_discount' => $validated['pwd_senior_discount'],
                'pwd_senior_discount_reason' => $validated['discount_reason'],
            ]);

            DB::commit();

            Log::info('PWD/Senior discount updated', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'pwd_senior_discount' => $validated['pwd_senior_discount'],
                'discount_reason' => $validated['discount_reason'] ?? null
            ]);

            return new ItemResponse(new BookingResource($booking->refresh()));
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Booking not found.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update PWD/Senior discount', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $bookingId,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to update PWD/Senior discount', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update special discount for a booking.
     */
    public function updateSpecialDiscount(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'special_discount' => 'required|numeric|min:0',
            'discount_reason' => 'required|string|max:500',
        ]);

        try {
            $booking = $this->bookingService->show($bookingId);

            DB::beginTransaction();

            // Update the special discount
            $booking->update([
                'special_discount' => $validated['special_discount'],
                'special_discount_reason' => $validated['discount_reason'],
            ]);

            DB::commit();

            Log::info('Special discount updated', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'special_discount' => $validated['special_discount'],
                'discount_reason' => $validated['discount_reason'] ?? null
            ]);

            return new ItemResponse(new BookingResource($booking->refresh()));
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Booking not found.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update special discount', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $bookingId,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to update special discount', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Change room unit assignment for a specific booking room.
     */
    public function changeRoomUnit(Request $request, $bookingId, $bookingRoomId)
    {
        $validated = $request->validate([
            'room_unit_id' => 'required|integer|exists:room_units,id',
        ]);

        try {
            $booking = $this->bookingService->show($bookingId);
            $bookingRoom = $booking->bookingRooms()->findOrFail($bookingRoomId);

            DB::beginTransaction();

            // Update the booking room with the new unit assignment
            $bookingRoom->room_unit_id = $validated['room_unit_id'];
            $bookingRoom->save();

            DB::commit();

            Log::info('Room unit assignment changed', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $booking->id,
                'booking_reference' => $booking->reference_number,
                'booking_room_id' => $bookingRoomId,
                'old_unit_id' => $bookingRoom->getOriginal('room_unit_id'),
                'new_unit_id' => $validated['room_unit_id'],
            ]);

            return new ItemResponse(new BookingResource($booking->refresh()));
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Booking or booking room not found.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to change room unit assignment', [
                'admin_user_id' => Auth::user()->id,
                'booking_id' => $bookingId,
                'booking_room_id' => $bookingRoomId,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to change room unit assignment', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
