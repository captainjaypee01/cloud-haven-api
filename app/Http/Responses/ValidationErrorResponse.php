<?php
namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ValidationErrorResponse extends Response
{
    public function __construct(string $message, array $errorData, int $status = JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
    {
        parent::__construct(
            data: [
                'message'   => $message,
                'errors'    => $errorData,
            ],
            status: $status
        );
    }
}