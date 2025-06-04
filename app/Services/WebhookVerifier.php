<?php

namespace App\Services;

use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;

class WebhookVerifier
{
    public function __construct(
        private readonly Webhook $webhook
    ) {}

    public function verify(string $payload, array $headers): array
    {
        return $this->webhook->verify($payload, $headers);
    }
}