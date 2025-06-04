<?php

use App\Contracts\Services\UserServiceInterface;
use App\Models\User;
use App\Services\WebhookVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;

describe('Clerk Webhook Controller Test', function () {

    beforeEach(function () {
        // Use current timestamp
        $this->currentTimestamp = now()->timestamp;

        $this->validHeaders = [
            // 'svix-id' => 'test_id',
            // 'svix-timestamp' => (string) $this->currentTimestamp,
            // 'svix-signature' => 'valid_signature',
            'svix-id' => 'msg_testid',
            'svix-timestamp' => $this->currentTimestamp,
            'svix-signature' => 'valid_signature',
        ];

        $this->baseEventData = function (string $type) {
            if($type === "user.deleted"){
                return [
                    'type' => $type,
                    'data' => ['id' => 'user_test123']
                ];
            }
            return [
                'type' => $type,
                'data' => [
                    'id' => 'user_test123',
                    'email_addresses' => [
                        [
                            'email_address' => 'test@example.com',
                            'created_at' => \Carbon\CarbonImmutable::createFromTimestampUTC(now()->getTimestamp() * 1000), // Clerk uses milliseconds
                            'linked_to' => [['id' => 'oauth_1', 'type' => 'google']],
                        ]
                    ],
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'image_url' => 'https://image.url',
                ]
            ];
        };

        // Properly mock Svix verification
        // Mock WebhookVerifier
        $this->mock(WebhookVerifier::class, function ($mock) {
            $mock->shouldReceive('verify')
                ->andReturnUsing(function ($payload, $headers) {
                    return json_decode($payload, true);
                });
        });
    });

    test('handles user.created event', function () {
        $this->mock(UserServiceInterface::class, function ($mock) {
            $mock->shouldReceive('createUserByClerk')
                ->once()
                ->withArgs(
                    fn($payload) =>
                    $payload['clerk_id'] === 'user_test123' &&
                        $payload['email'] === 'test@example.com'
                );
        });

        $this->postJson(
            route('webhook.clerk'),
            ($this->baseEventData)('user.created'),
            $this->validHeaders
        )->assertOk()->assertJson(['success' => true]);
    });

    test('handles user.updated event', function () {
        $this->mock(UserServiceInterface::class, function ($mock) {
            $user = new User();
            $mock->shouldReceive('showByClerkId')
                ->once()
                ->andReturn($user);

            $mock->shouldReceive('updateByClerkId')
                ->once()
                ->with('user_test123', Mockery::type('array'));
        });

        $this->postJson(
            route('webhook.clerk'),
            ($this->baseEventData)('user.updated'),
            $this->validHeaders
        )->assertOk();
    });

    test('handles user.deleted event', function () {
        $this->mock(UserServiceInterface::class, function ($mock) {
            $mock->shouldReceive('deleteByClerkId')
                ->once()
                ->with('user_test123');
        });

        $this->postJson(
            route('webhook.clerk'),
            ($this->baseEventData)('user.deleted'),
            $this->validHeaders
        )->assertOk();
    });

    test('rejects invalid signature', function () {
        Log::spy();

        // Override mock for this specific test with proper exception
        $this->mock(WebhookVerifier::class, function ($mock) {
            $mock->shouldReceive('verify')
                ->andThrow(\Svix\Exception\WebhookVerificationException::class, 'Invalid signature');
        });

        $this->postJson(
            route('webhook.clerk'),
            ($this->baseEventData)('user.created'),
            $this->validHeaders
        )->assertUnauthorized()->assertJson(['error' => 'Invalid signature']);

        Log::shouldHaveReceived('error')
            ->withArgs(fn($message) => str_contains($message, 'Webhook verification failed'))
            ->once();
    });

    test('handles missing user on update', function () {
        Log::spy();

        $this->mock(UserServiceInterface::class, function ($mock) {
            $mock->shouldReceive('showByClerkId')
                ->once()
                ->andThrow(new ModelNotFoundException);
        });

        $this->postJson(
            route('webhook.clerk'),
            ($this->baseEventData)('user.updated'),
            $this->validHeaders
        )->assertNotFound()->assertJson(['error' => 'User not found']);

        Log::shouldHaveReceived('error')
            ->withArgs(fn($message) => str_contains($message, 'User not found'))
            ->once();
    });

    test('handles generic errors', function () {
        Log::spy();

        $this->mock(UserServiceInterface::class, function ($mock) {
            $mock->shouldReceive('createUserByClerk')
                ->once()
                ->andThrow(new RuntimeException('Service failed'));
        });

        $response = $this->postJson(
            route('webhook.clerk'),
            ($this->baseEventData)('user.created'),
            $this->validHeaders
        );

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);

        Log::shouldHaveReceived('error')
            ->withArgs(fn($message) => str_contains($message, 'Webhook processing error'))
            ->once();
    });
});
