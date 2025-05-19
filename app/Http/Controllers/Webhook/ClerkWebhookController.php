<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClerkWebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        // 1. Verify signature
        $payload = file_get_contents('php://input');
        $secret = env('CLERK_WEBHOOK_SECRET');
        // 1. Read raw body
        // $payload = $request->getContent();

        // 2. Gather Svix headers
        $headers = [
            'svix-id' => $request->header('svix-id'),
            'svix-timestamp' => $request->header('svix-timestamp'),
            'svix-signature' => $request->header('svix-signature'),
        ];

        if (!$this->verifySignature($headers['svix-signature'], $payload, $secret)) {
            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        // 2. Process event
        $body = json_decode($payload, true);
        \Log::info($body);

        $data = $body['data'];
        \Log::info($data);

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
        }

        return response()->json(['success' => true]);
    }
    
    private function verifySignature(string $signatureHeader, string $payload, string $secret): bool
    {
        // 1. Split the signature header
        $parts = explode(',', $signatureHeader, 3);

        // Should have 3 parts: v1, timestamp, signature
        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            return false;
        }

        $version = $parts[0];
        $timestamp = $parts[1];
        $receivedSignature = $parts[2];

        // 2. Prepare the secret (Clerk secrets are base64 encoded with "whsec_" prefix)
        $secret = substr($secret, 6); // Remove "whsec_" prefix
        $decodedSecret = base64_decode($secret);

        // 3. Create the signed content
        $signedContent = "{$timestamp}.{$payload}";

        // 4. Compute expected signature
        $expectedSignature = hash_hmac(
            'sha256',
            $signedContent,
            $decodedSecret
        );

        // 5. Compare signatures
        return hash_equals($expectedSignature, $receivedSignature);
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
