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
        $requestData = $request->all();
        
        Log::info('Starting Day Tour booking creation', [
            'guest_email' => $requestData['guest_email'] ?? null,
            'guest_name' => $requestData['guest_name'] ?? null,
            'date' => $requestData['date'] ?? null,
            'total_adults' => $requestData['total_adults'] ?? null,
            'total_children' => $requestData['total_children'] ?? null,
            'user_authenticated' => auth()->check(),
            'user_id' => auth()->check() ? auth()->user()->id : null
        ]);
        
        try {
            // Validate and create DTO
            $dto = DayTourBookingRequestDTO::from($requestData);
            
            // Get user ID if authenticated
            $userId = auth()->check() ? auth()->user()->id : null;
            
            // Create booking
            $booking = $this->createBookingAction->execute($dto, $userId);
            
            Log::info('Day Tour booking created successfully', [
                'booking_id' => $booking->id,
                'reference_number' => $booking->reference_number,
                'date' => $booking->check_in_date,
                'guest_email' => $booking->guest_email,
                'guest_name' => $booking->guest_name,
                'total_adults' => $booking->total_adults,
                'total_children' => $booking->total_children,
                'total_price' => $booking->total_price,
                'final_price' => $booking->final_price,
                'user_id' => $userId
            ]);
            
            return new ItemResponse(
                new PublicBookingResource($booking),
                JsonResponse::HTTP_CREATED
            );
            
        } catch (ValidationException $e) {
            Log::warning('Day Tour booking validation failed', [
                'error' => $e->getMessage(),
                'validation_errors' => $e->errors(),
                'request_data' => $requestData
            ]);
            throw $e;
        } catch (InvalidArgumentException $e) {
            Log::warning('Day Tour booking validation failed', [
                'error' => $e->getMessage(),
                'request_data' => $requestData
            ]);
            
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Day Tour booking creation failed', [
                'error' => $e->getMessage(),
                'request_data' => $requestData
            ]);
            
            return new ErrorResponse(
                'Unable to create Day Tour booking. Please try again.',
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
