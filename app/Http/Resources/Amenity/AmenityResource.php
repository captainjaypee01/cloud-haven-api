<?php

namespace App\Http\Resources\Amenity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AmenityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name'              => $this->name,
            'description'       => $this->description,
            'icon'              => $this->icon,
            'price'             => $this->price,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
