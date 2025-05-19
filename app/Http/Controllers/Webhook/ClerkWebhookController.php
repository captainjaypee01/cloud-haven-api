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
        \Log::info($request);
        // 1. Verify signature
        $signature = $request->header('svix-signature');
        $payload = file_get_contents('php://input');
        $secret = env('CLERK_WEBHOOK_SECRET');

        if (!$this->verifySignature($signature, $payload, $secret)) {
            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        // 2. Process event
        $body = json_decode($payload, true);
        \Log::info($data);

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

    private function verifySignature($signature, $payload, $secret)
    {
        // Clerk uses Svix libraries for signing
        // Implementation example:
        $parts = explode(',', $signature, 2);
        $timestamp = explode('=', $parts[0], 2)[1];
        $signature = explode('=', $parts[1], 2)[1];

        $signedContent = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedContent, $secret);

        return hash_equals($expectedSignature, $signature);
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
