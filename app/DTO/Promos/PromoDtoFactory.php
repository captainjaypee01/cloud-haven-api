<?php

namespace App\DTO\Promos;

class PromoDtoFactory
{
    public function newPromo(array $data): NewPromo
    {
        return new NewPromo(
            code: $data['code'],
            discount_type: $data['discount_type'],
            discount_value: $data['discount_value'],
            expires_at: $data['expires_at'] ?? null,
            max_uses: $data['max_uses'] ?? null,
            uses_count: 0,
            active: $data['active'] === "active" ?? false,
        );
    }

    public function updatePromo(array $data): UpdatePromo
    {
        return new UpdatePromo(
            code: $data['code'],
            discount_type: $data['discount_type'],
            discount_value: $data['discount_value'],
            expires_at: $data['expires_at'] ?? null,
            max_uses: $data['max_uses'] ?? null,
            active: $data['active'] === "active" ?? false,
            // 'active' not handled here (use separate status toggle endpoint)
        );
    }
}
