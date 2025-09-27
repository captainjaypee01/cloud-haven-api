<?php

namespace App\Http\Resources\Promo;

use Illuminate\Http\Resources\Json\JsonResource;

class PromoResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'discount_type'     => $this->discount_type,
            'discount_value'    => $this->discount_value,
            'scope'             => $this->scope,
            'title'             => $this->title,
            'description'       => $this->description,
            'image_url'         => $this->image_url,
            'starts_at'         => $this->starts_at
                ? $this->convertToUserTimezone($this->starts_at)
                : null,
            'ends_at'           => $this->ends_at
                ? $this->convertToUserTimezone($this->ends_at)
                : null,
            'expires_at'        => $this->expires_at
                ? $this->expires_at->format('Y-m-d H:i:s')
                : null,
            'max_uses'          => $this->max_uses,
            'uses_count'        => $this->uses_count ?? 0,
            'exclusive'         => (bool) $this->exclusive,
            'excluded_days'     => $this->excluded_days,
            'per_night_calculation' => (bool) $this->per_night_calculation,
            'excluded_day_names' => $this->getExcludedDayNames(),
            'created_at'        => $this->created_at->format('Y-m-d H:i:s'),
            // Return status as string to match front-end expectations
            'active'            => $this->active ? 'active' : 'inactive',
        ];
    }

    /**
     * Convert UTC datetime to user timezone (Asia/Singapore)
     *
     * @param \Carbon\Carbon $datetime
     * @return string
     */
    private function convertToUserTimezone($datetime): string
    {
        // Convert from UTC to Asia/Singapore timezone
        return $datetime->setTimezone('Asia/Singapore')->format('Y-m-d H:i:s');
    }
}
