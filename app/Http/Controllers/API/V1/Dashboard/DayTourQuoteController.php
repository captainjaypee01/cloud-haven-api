<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Actions\DayTour\ComputeDayTourQuoteAction;
use App\DTO\DayTour\DayTourQuoteRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;

class DayTourQuoteController extends Controller
{
    public function __construct(
        private ComputeDayTourQuoteAction $computeQuoteAction
    ) {}

    public function quote(Request $request)
    {
        try {
            // Validate and create DTO
            $dto = DayTourQuoteRequestDTO::from($request->all());
            
            // Compute quote
            $quote = $this->computeQuoteAction->execute($dto);
            
            return new ItemResponse($quote->toArray());
            
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            return new ErrorResponse($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return new ErrorResponse(
                'Unable to generate quote. Please try again.',
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
