<?php

namespace App\Http\Resources\Room;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PublicRoomCollection extends ResourceCollection
{
    public $collects = PublicRoomResource::class;
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        // If paginated (LengthAwarePaginator or Paginator)
        if ($this->resource instanceof \Illuminate\Pagination\AbstractPaginator) {
            return [
                'data' => $this->collection,
                'meta' => [
                    'total'        => $this->total(),
                    'count'        => $this->count(),
                    'per_page'     => $this->perPage(),
                    'current_page' => $this->currentPage(),
                    'total_pages'  => $this->lastPage(),
                ],
                'links' => [
                    'self' => $request->fullUrl(),
                ],
            ];
        }

        // If NOT paginated (plain Collection)
        return [
            'data' => $this->collection,
            'meta' => [
                'count' => $this->collection->count(),
            ],
            'links' => [
                'self' => $request->fullUrl(),
            ],
        ];
    }

    public function with($request)
    {
        return [
            'server_time' => now()->toDateTimeString(),
        ];
    }
}
