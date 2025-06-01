<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'description'       => $this->description,
            'quantity'          => $this->quantity,
            'max_guests'        => $this->max_guests,
            'extra_guest_fee'   => $this->extra_guest_fee,
            'status'            => $this->status,
            // 'images'            => ImageResource::collection($this->images),
            // 'amenities'         => AmenityResource::collection($this->amenities),
            'created_at'        => $this->created_at->toDateTimeString(),
            'updated_at'        => $this->updated_at->toDateTimeString(),
        ];
    }
}
