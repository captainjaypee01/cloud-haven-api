<?php

namespace App\Http\Resources\MealPrice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class PublicMealPriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }

    /**
     * Add top-level metadata and links.
     */
    public function with(Request $request): array
    {
        $links = ['self' => $request->fullUrl()];

        if ($this->resource instanceof LengthAwarePaginator) {
            return [];
        }

        // Non-paginated (“all”) response
        return [];
    }
}
