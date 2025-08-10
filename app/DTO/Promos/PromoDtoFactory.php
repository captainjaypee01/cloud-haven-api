<?php

namespace App\DTO\Promos;

class PromoDtoFactory
{
    /**
     * Build a NewPromo DTO from request data.
     *
     * @param array $data
     * @return NewPromo
     */
    public function newPromo(array $data): NewPromo
    {
        return new NewPromo(
            code: $data['code'],
            title: $data['title'],
            description: $data['description'] ?? null,
            scope: $data['scope'] ?? null,
            discount_type: $data['discount_type'],
            discount_value: (float) $data['discount_value'],
            expires_at: $data['expires_at'] ?? null,
            max_uses: $data['max_uses'] ?? null,
            image_url: $data['image_url'] ?? null,
            exclusive: (bool) ($data['exclusive'] ?? false),
            uses_count: 0,
            active: ($data['active'] ?? 'inactive') === 'active',
        );
    }

    /**
     * Build an UpdatePromo DTO from request data.  Fields are passed
     * directly; optional fields may be omitted and will default to null
     * or false.  Active is derived from the `active` string value.
     *
     * @param array $data
     * @return UpdatePromo
     */
    public function updatePromo(array $data): UpdatePromo
    {
        return new UpdatePromo(
            code: $data['code'],
            title: $data['title'],
            description: $data['description'] ?? null,
            scope: $data['scope'] ?? null,
            discount_type: $data['discount_type'],
            discount_value: (float) $data['discount_value'],
            expires_at: $data['expires_at'] ?? null,
            max_uses: $data['max_uses'] ?? null,
            image_url: $data['image_url'] ?? null,
            exclusive: (bool) ($data['exclusive'] ?? false),
            active: ($data['active'] ?? 'inactive') === 'active',
        );
    }
}
