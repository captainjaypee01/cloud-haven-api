<?php

namespace App\Http\Resources\Image;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'alt_text' => $this->alt_text,
            'image_url' => $this->image_url,
            'secure_image_url' => $this->secure_image_url,
            'provider' => $this->provider,
            'public_id' => $this->public_id,
            'width' => $this->width,
            'height' => $this->height,
            'order' => $this->order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
