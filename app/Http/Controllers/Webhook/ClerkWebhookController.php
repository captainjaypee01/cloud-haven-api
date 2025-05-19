<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ClerkWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            // Get headers
            $signature = $request->header('svix-signature');
            $timestamp = $request->header('svix-timestamp');
            $eventId = $request->header('svix-id');

            // Get raw payload
            $payload = $request->getContent();

            // Verify headers exist
            if (!$signature || !$timestamp || !$eventId) {
                Log::error('Missing Svix headers', ['headers' => $request->headers->all()]);
                return response()->json(['error' => 'Invalid headers'], 400);
            }

            // Verify signature
            $secret = env('CLERK_WEBHOOK_SECRET');
            if (!$this->verifySignature($signature, $timestamp, $payload, $secret)) {
                Log::error('Invalid signature', [
                    'received' => $signature,
                    'expected' => $this->calculateExpectedSignature($timestamp, $payload, $secret)
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Verify timestamp freshness (5 minutes tolerance)
            if (abs(time() - (int)$timestamp) > 300) {
                Log::error('Stale timestamp', [
                    'server_time' => time(),
                    'webhook_time' => (int)$timestamp
                ]);
                return response()->json(['error' => 'Expired request'], 401);
            }

            // Process payload
            $body = json_decode($payload, true);
            $data = $body['data'];
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON payload');
                return response()->json(['error' => 'Invalid payload'], 400);
            }

            // Handle events
            $userService = new UserService();
            $userData = [
                'clerk_id'  => $data['id'],
                'email'  => $data['email_addresses'][0]['email_address'],
                'first_name'  => $data['first_name'],
                'last_name'  => $data['last_name'],
                'image'  => $data['image_url'],
            ];
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
                    // Add other cases
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('Webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);
            return response()->json(['error' => 'Server error'], 500);
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
