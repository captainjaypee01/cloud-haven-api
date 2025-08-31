<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\BookingServiceInterface;
use App\DTO\Bookings\BookingData;
use App\Exceptions\RoomNotAvailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\BookingRequest;
use App\Http\Resources\Booking\PublicBookingResource;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use App\Models\Booking;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;

class BookingController extends Controller
{
    public function __construct(private BookingServiceInterface $bookingService) {}

    public function store(BookingRequest $request)
    {
        $validated = $request->validated();
        $bookingData = BookingData::from($validated);

        // Get user ID - could be null for guest bookings
        $userId = Auth::id();
        
        try {
            $result = $this->bookingService->createBooking($bookingData, $userId);
            // Load booking_rooms for response if needed
            $booking = $result instanceof Booking ? $result->load('bookingRooms') : $result;
        } catch (RoomNotAvailableException $e) {
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_CONFLICT);
        }

        return new ItemResponse(new PublicBookingResource($booking), JsonResponse::HTTP_CREATED);
    }

    public function showByReferenceNumber($referenceNumber)
    {
        try {
            $data = $this->bookingService->showByReferenceNumber($referenceNumber);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Booking not found.');
        }
        return new ItemResponse(new PublicBookingResource($data));
    }
}
