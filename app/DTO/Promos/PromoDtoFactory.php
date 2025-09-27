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
            starts_at: $this->convertToUtc($data['starts_at'] ?? null),
            ends_at: $this->convertToUtc($data['ends_at'] ?? null),
            expires_at: $data['expires_at'] ?? null,
            max_uses: $data['max_uses'] ?? null,
            image_url: $data['image_url'] ?? null,
            exclusive: (bool) ($data['exclusive'] ?? false),
            uses_count: 0,
            active: ($data['active'] ?? 'inactive') === 'active',
            excluded_days: $this->processExcludedDays($data['excluded_days'] ?? null),
            per_night_calculation: (bool) ($data['per_night_calculation'] ?? false),
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
            starts_at: $this->convertToUtc($data['starts_at'] ?? null),
            ends_at: $this->convertToUtc($data['ends_at'] ?? null),
            expires_at: $data['expires_at'] ?? null,
            max_uses: $data['max_uses'] ?? null,
            image_url: $data['image_url'] ?? null,
            exclusive: (bool) ($data['exclusive'] ?? false),
            active: ($data['active'] ?? 'inactive') === 'active',
            excluded_days: $this->processExcludedDays($data['excluded_days'] ?? null),
            per_night_calculation: (bool) ($data['per_night_calculation'] ?? false),
        );
    }

    /**
     * Process excluded days array from request data
     *
     * @param mixed $excludedDays
     * @return array|null
     */
    private function processExcludedDays($excludedDays): ?array
    {
        if (is_null($excludedDays) || $excludedDays === '') {
            return null;
        }

        if (is_string($excludedDays)) {
            // Handle JSON string input
            $decoded = json_decode($excludedDays, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_map('intval', $decoded);
            }
            return null;
        }

        if (is_array($excludedDays)) {
            // Ensure all values are integers and within valid range (0-6)
            $filtered = array_filter($excludedDays, function($day) {
                return is_numeric($day) && $day >= 0 && $day <= 6;
            });
            return empty($filtered) ? null : array_map('intval', $filtered);
        }

        return null;
    }

    /**
     * Convert datetime from user timezone (Asia/Singapore or Asia/Manila) to UTC
     *
     * @param string|null $datetime
     * @return string|null
     */
    private function convertToUtc(?string $datetime): ?string
    {
        if (!$datetime) {
            return null;
        }

        try {
            // Create Carbon instance from the datetime string, assuming it's in Asia/Singapore timezone
            $carbon = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $datetime, 'Asia/Singapore');
            
            // Convert to UTC
            $carbon->utc();
            
            return $carbon->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            // If parsing fails, return null
            return null;
        }
    }
}
