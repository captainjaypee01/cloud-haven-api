<?php
namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResponse extends Response
{
    public function __construct(JsonResource|array $data, int $status = JsonResponse::HTTP_OK)
    {
        parent::__construct($data, $status);
    }
}
