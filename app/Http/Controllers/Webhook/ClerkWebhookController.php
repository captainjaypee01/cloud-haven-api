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
            $userData = [
                'clerk_id'  => $data['id'],
                'email'  => $data['email_addresses'][0]['email_address'],
                'first_name'  => $data['first_name'],
                'last_name'  => $data['last_name'],
                'image'  => $data['image_url'],
            ];
            // 4. Handle Clerk events
            switch ($body['type']) {
                case 'user.created':
                    $userService->store($userData);
                    break;

                case 'user.updated':
                    $userService->updateByClerkId($data['id'], $userData);
                    break;

                case 'user.deleted':
                    $userService->deleteByClerkId($data['id']);
                    break;
            }

            return response()->json(['success' => true]);
        } catch (WebhookVerificationException $e) {
            Log::error('Webhook verification failed: ' . $e->getMessage(), [
                'headers' => $request->headers->all(),
                'payload' => $payload ?? null
            ]);
            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            Log::error('Webhook processing error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $payload ?? null
            ]);
            return response()->json(['error' => 'Server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function verifySignature(string $receivedSignature, string $timestamp, string $payload, string $secret): bool
    {
        // 1. Prepare the secret
        $secret = substr($secret, 6); // Remove "whsec_" prefix
        $decodedSecret = base64_decode($secret);

        // 2. Create the signed content
        $signedContent = "{$timestamp}.{$payload}";

        // 3. Compute expected signature
        $expectedSignature = hash_hmac(
            'sha256',
            $signedContent,
            $decodedSecret
        );

        // 4. Compare signatures
        return hash_equals($expectedSignature, $receivedSignature);
    }

    // Helper to calculate expected signature for debugging
    private function calculateExpectedSignature(string $timestamp, string $payload, string $secret): string
    {
        $secret = substr($secret, 6);
        $decodedSecret = base64_decode($secret);
        return hash_hmac('sha256', "{$timestamp}.{$payload}", $decodedSecret);
    }

    private function handleUserCreated(array $userData)
    {
        // Example implementation
        \App\Models\User::updateOrCreate(
            ['clerk_id' => $userData['id']],
            [
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'email' => $userData['email_addresses'][0]['email_address'],
                // Add other fields
            ]
        );
    }

    private function handleUserUpdated(array $userData)
    {
        \App\Models\User::where('clerk_id', $userData['id'])
            ->update([
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'email' => $userData['email_addresses'][0]['email_address'],
                // Update other fields
            ]);
    }

    private function handleUserDeleted(array $userData)
    {
        // Soft delete example
        \App\Models\User::where('clerk_id', $userData['id'])
            ->delete();
    }
}
