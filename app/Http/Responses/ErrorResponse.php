<?php
namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorResponse extends Response
{
    public function __construct(string $message, int $status = JsonResponse::HTTP_NOT_FOUND)
    {
        parent::__construct(
            data: ['error' => $message],
            status: $status
        );
    }
}