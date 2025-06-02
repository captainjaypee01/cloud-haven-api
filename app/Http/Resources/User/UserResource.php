<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'clerk_id'          => $this->clerk_id,
            'email'             => $this->email,
            'first_name'        => $this->first_name,
            'last_name'         => $this->last_name,
            'country_code'      => $this->country_code,
            'contact_number'    => $this->contact_number,
            'image'             => $this->image,
            'created_at'        => $this->created_at->toDateTimeString(),
            'updated_at'        => $this->updated_at->toDateTimeString(),
            'providers'         => $this->providers->map(fn($p) => [
                'provider'    => $p->provider,
                'provider_id' => $p->provider_id,
            ]),
        ];
    }
}
