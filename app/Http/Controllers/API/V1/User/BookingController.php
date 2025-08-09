<?php

namespace App\Http\Controllers\API\V1\User;

use App\Contracts\Services\BookingServiceInterface;
use App\Exceptions\BookingAlreadyClaimedException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Booking\BookingCollection;
use App\Http\Resources\Booking\PublicBookingResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingServiceInterface $bookingService
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function listByUser(Request $request)
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->bookingService->listByUser(auth()->user()->id, $filters);
        return new CollectionResponse(new BookingCollection($paginator), JsonResponse::HTTP_OK);
    }

    public function claim(string $referenceNumber)
    {
        try {
            $booking = $this->bookingService->claimBooking($referenceNumber, auth()->user()->id);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Booking not found.', JsonResponse::HTTP_NOT_FOUND);
        } catch (BookingAlreadyClaimedException $e) {
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_CONFLICT);
        }
        return new ItemResponse(new PublicBookingResource($booking));
    }
}
