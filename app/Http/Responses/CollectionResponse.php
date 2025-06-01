<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class CollectionResponse extends Response
{
    public function __construct(
        private readonly ResourceCollection $data,
        private readonly int $status = JsonResponse::HTTP_OK,
    ) {
        parent::__construct($data, $status);
    }
}
