<?php

namespace App\DTO\Users;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class UpdateUser extends Data
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public ?string $clerk_id,
        public string $email,
        public string $first_name,
        public string $last_name,
        public string $role,
        public ?string $password,
        public ?string $country_code,
        public ?string $contact_number,
        public ?string $image_url,
        #[Nullable]
        #[DateFormat('Y-m-d H:i:s')]
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public ?CarbonImmutable $email_verified_at,
        public array $linkedProviders // an array of [ ['type'=>'oauth_google','id'=>'…'], … ]
    ) {}
}
