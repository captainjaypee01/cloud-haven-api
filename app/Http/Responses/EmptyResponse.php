<?php
namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class EmptyResponse extends Response
{
    public function __construct(int $status = JsonResponse::HTTP_NO_CONTENT)
    {
        parent::__construct(null, $status);
    }
}
