<?php

namespace App\Http\Resources\MealPrice;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PublicMealPriceCollection extends ResourceCollection
{
    /** @var string The resource used for each item */
    public $collects = PublicMealPriceResource::class;
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
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
        return [
        ];
    }
}
