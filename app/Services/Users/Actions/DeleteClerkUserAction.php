<?php

namespace App\Services\Users\Actions;

use App\Contracts\Users\DeleteClerkUserContract;
use App\Models\User;
use Clerk\Backend;
use Illuminate\Support\Facades\Log;

final class DeleteClerkUserAction implements DeleteClerkUserContract
{
    public function handle(User $user): void
    {
        $sdk = Backend\ClerkBackend::builder()
            ->setSecurity(
                config('services.clerk.secret_key')
            )
            ->build();

        try {

            $response = $sdk->users->delete(
                userId: $user->clerk_id,
            );

        }
        catch(\Clerk\Backend\Models\Errors\ClerkErrorsThrowable $throw) {
            Log::error('Clerk createUser failed', [
                'status' => $throw->getCode(),
                'errors' => $throw->getMessage() ?? null,
            ]);
            $message = json_decode($throw->getMessage());
            $messageError = $message->errors ? $message->errors[0]->message : 'Unable to create a user for Clerk';
            throw new \Exception($messageError, 422);
        }
        catch (\Throwable $e) {
            Log::error($e->getMessage());
            throw new \Exception($e->getMessage(), $e->getCode());
        }

    }
}
