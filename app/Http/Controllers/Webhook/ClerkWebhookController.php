<?php

namespace App\Http\Controllers\Webhook;

use App\Contracts\Services\UserServiceInterface;
use App\Http\Controllers\Controller;
use App\Services\WebhookVerifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;
use Symfony\Component\HttpFoundation\Response;

class ClerkWebhookController extends Controller
{
    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly WebhookVerifier $webhookVerifier
    ) {}

    public function __invoke(Request $request)
    {
        try {
            // 1. No need to initialize as we inject a webhook verifier
            // $webhook = new Webhook(env('CLERK_WEBHOOK_SECRET'));

            // 2. Get raw payload and headers
            $payload = $request->getContent();
            $headers = [
                'svix-id' => $request->header('svix-id'),
                'svix-timestamp' => $request->header('svix-timestamp'),
                'svix-signature' => $request->header('svix-signature'),
            ];

            // 3. Verify and process payload
            $body = $this->webhookVerifier->verify($payload, $headers);
            $data = $body['data'];

            // Handle events
            // 4. Handle Clerk events
            switch ($body['type']) {
                case 'user.created':
                case 'user.updated':
                    $payload = [
                        'clerk_id'              => $data['id'],
                        'email'                 => $data['email_addresses'][0]['email_address'],
                        'first_name'            => $data['first_name'],
                        'last_name'             => $data['last_name'],
                        'role'                  => 'user',
                        'country_code'          => '',
                        'contact_number'        => '',
                        'image_url'             => $data['image_url'],
                        'password'              => '',
                        'email_verified_at'     => \Carbon\CarbonImmutable::createFromTimestampMsUTC($data['email_addresses'][0]['created_at']), //'2025-05-31 18:53:35',
                        'linkedProviders'       => $data['email_addresses'][0]['linked_to'],
                    ];
                    if ($body['type'] === 'user.created') {
                        $this->userService->createUserByClerk($payload);
                    } else {
                        $user = $this->userService->showByClerkId($data['id']);
                        $payload['role'] = $user->role ?? 'user';
                        $this->userService->updateByClerkId($data['id'], $payload);
                    }
                    break;

                case 'user.deleted':
                    $this->userService->deleteByClerkId($data['id']);
                    break;
            }

            return response()->json(['success' => true]);
        } catch (ModelNotFoundException $e) {
            Log::error('User not found: ' . $e->getMessage(), [
                'headers' => $request->headers->all(),
            ]);
            return response()->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        } catch (WebhookVerificationException $e) {
            Log::error('Webhook verification failed: ' . $e->getMessage(), [
                'headers' => $request->headers->all(),
                // 'payload' => $payload ?? null
            ]);
            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            Log::error('Webhook processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                // 'payload' => $payload ?? null
            ]);
            return response()->json(['error' => 'Server error', 'trace' => $e->getTraceAsString()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
