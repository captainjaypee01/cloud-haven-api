<?php

namespace App\Services\Users\Actions;

use App\Contracts\Users\CreateClerkUserContract;
use Clerk\Backend;
use Clerk\Backend\Models\Operations;
use Clerk\Backend\Models\Components\User as ClerkUser;
use Illuminate\Support\Facades\Log;

final class CreateClerkUserAction implements CreateClerkUserContract
{
    public function handle(array $data): ?ClerkUser
    {
        $sdk = Backend\ClerkBackend::builder()
            ->setSecurity(
                config('services.clerk.secret_key')
            )
            ->build();

        $request = new Operations\CreateUserRequestBody(
            emailAddress: [
                $data['email'] ?? null,
            ],
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            publicMetadata: ['role' => $data['role']],
            password: $data['password'],
        );

        try {

            $response = $sdk->users->create(
                request: $request
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
