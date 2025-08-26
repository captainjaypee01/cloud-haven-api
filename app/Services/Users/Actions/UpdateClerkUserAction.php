<?php

namespace App\Services\Users\Actions;

use App\Contracts\Users\UpdateClerkUserContract;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Clerk\Backend;
use Clerk\Backend\Models\Operations;
use Clerk\Backend\Models\Components\User as ClerkUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class UpdateClerkUserAction implements UpdateClerkUserContract
{
    public function handle(User $user, array $data): ?ClerkUser
    {
        $sdk = Backend\ClerkBackend::builder()
            ->setSecurity(
                config('services.clerk.secret_key')
            )
            ->build();

        $request = new Operations\UpdateUserRequestBody(
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            publicMetadata: ['role' => $data['role']],
            privateMetadata: ['role' => $data['role']],
        );

        try {

            $response = $sdk->users->update(
                requestBody: $request,
                userId: $user->clerk_id,
            );
            $user = $response->user;
            return $user;
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
