<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Actions\DayTour\CreateDayTourBookingAction;
use App\DTO\DayTour\DayTourBookingRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\Booking\PublicBookingResource;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;

class DayTourBookingController extends Controller
{
    public function __construct(
        private CreateDayTourBookingAction $createBookingAction
    ) {}

    public function create(Request $request)
    {
        try {
            // Validate and create DTO
            $dto = DayTourBookingRequestDTO::from($request->all());
            
            // Get user ID if authenticated
            $userId = auth()->check() ? auth()->user()->id : null;
            
            // Create booking
            $booking = $this->createBookingAction->execute($dto, $userId);
            
            Log::info('Day Tour booking created successfully', [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'date' => $booking->check_in_date,
                'guest_email' => $booking->guest_email
            ]);
            
            return new ItemResponse(
                new PublicBookingResource($booking),
                JsonResponse::HTTP_CREATED
            );
            
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            Log::warning('Day Tour booking validation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Day Tour booking creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return new ErrorResponse(
                'Unable to create Day Tour booking. Please try again.',
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
