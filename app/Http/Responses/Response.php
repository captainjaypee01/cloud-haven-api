<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class Response implements Responsable
{
    public function __construct(
        private readonly JsonResource|ResourceCollection|array $data,
        private readonly int $status = JsonResponse::HTTP_OK
    ) {}

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(data: $this->data, status: $this->status);
    }
}
