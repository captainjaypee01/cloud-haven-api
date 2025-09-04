<?php

namespace App\Http\Resources\Room;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomAvailabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'room_type_id' => $this->resource['room_type_id'],
            'room_name' => $this->resource['room_name'],
            'available_units' => $this->resource['available_units'],
            'pending' => $this->resource['pending'] ?? 0,
            'confirmed' => $this->resource['confirmed'] ?? 0,
            'maintenance' => $this->resource['maintenance'] ?? 0,
            'total_units' => $this->resource['total_units'] ?? 0,
            'check_in' => $this->resource['check_in'],
            'check_out' => $this->resource['check_out'],
        ];
    }
}
