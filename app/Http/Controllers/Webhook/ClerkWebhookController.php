<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Svix\Exception\WebhookVerificationException;
use Svix\Webhook;
use Symfony\Component\HttpFoundation\Response;

class ClerkWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $userData = [];
        try {
            // 1. Initialize Svix Webhook with secret
            $webhook = new Webhook(env('CLERK_WEBHOOK_SECRET'));

            // 2. Get raw payload and headers
            $payload = $request->getContent();
            $headers = [
                'svix-id' => $request->header('svix-id'),
                'svix-timestamp' => $request->header('svix-timestamp'),
                'svix-signature' => $request->header('svix-signature'),
            ];

            // 3. Verify and process payload
            $body = $webhook->verify($payload, $headers);
            $data = $body['data'];

            // Handle events
            $userService = new UserService();
            // 4. Handle Clerk events
            switch ($body['type']) {
                case 'user.created':
                    $userService->createUserByClerk($data);
                    break;

                case 'user.updated':
                    $userService->updateByClerkId($data['id'], $data);
                    break;

                case 'user.deleted':
                    $userService->deleteByClerkId($data['id']);
                    break;
            }

            return response()->json(['success' => true]);
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
