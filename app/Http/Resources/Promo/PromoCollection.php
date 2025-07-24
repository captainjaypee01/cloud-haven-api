<?php

namespace App\Http\Resources\Promo;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PromoCollection extends ResourceCollection
{
    public $collects = PromoResource::class;
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray($request): array
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
