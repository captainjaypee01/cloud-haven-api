<?php
namespace App\Http\Resources\Promo;

use Illuminate\Http\Resources\Json\JsonResource;

class PromoResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'code'           => $this->code,
            'discount_type'  => $this->discount_type,
            'discount_value' => $this->discount_value,
            'expires_at'     => $this->expires_at 
                                 ? $this->expires_at->format('Y-m-d H:i:s') 
                                 : null,
            'max_uses'       => $this->max_uses,
            'uses_count'     => $this->uses_count ?? 0,
            'created_at'     => $this->created_at->format('Y-m-d H:i:s') ,
            // Return status as string to match front-end expectations
            'active'         => $this->active ? 'active' : 'inactive',
        ];
    }
}
