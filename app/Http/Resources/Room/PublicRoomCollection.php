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
        // 'data' is auto-wrapped, but you can add meta or links here
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
}
