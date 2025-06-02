<?php

namespace App\DTO\Users;

class UserDtoFactory
{
    public function newUser(array $data): NewUser
    {
        /*
         Example $data comes from Clerk webhook:
         [
           'id' => 'clerk_user_id',
           'email_addresses' => [
             ['email_address'=>'user@example.com','linked_to'=>[ ['type'=>'oauth_google','id'=>'google-id'], … ] ]
           ],
           'first_name'=>'John',
           'last_name'=>'Doe',
           'image_url'=>'https://…',
         ]
        */
        $emailEntry     = $data['email_addresses'][0] ?? [];
        $linkedProviders = $emailEntry['linked_to'] ?? [];

        return new NewUser(
            clerk_id: $data['id'],
            role: $data['role'],
            email: $emailEntry['email_address'] ?? '',
            first_name: $data['first_name'] ?? '',
            last_name: $data['last_name'] ?? '',
            password: $data['password'] ?? null,
            country_code: $data['country_code'] ?? null,
            contact_number: $data['contact_number'] ?? null,
            image_url: $data['image_url'] ?? null,
            email_verified_at: $data['email_verified_at'] ?? null,
            linkedProviders: $linkedProviders,
        );
    }

    public function updateUser(array $data): UpdateUser
    {
        $emailEntry = $data['email_addresses'][0] ?? [];
        $linkedProviders = $emailEntry['linked_to'] ?? [];

        return new UpdateUser(
            clerk_id: $data['id'],
            role: $data['role'],
            email: $emailEntry['email_address'] ?? '',
            first_name: $data['first_name'] ?? '',
            last_name: $data['last_name'] ?? '',
            password: $data['password'] ?? null,
            country_code: $data['country_code'] ?? null,
            contact_number: $data['contact_number'] ?? null,
            image_url: $data['image_url'] ?? null,
            email_verified_at: $data['email_verified_at'] ?? null,
            linkedProviders: $linkedProviders ?? null
        );
    }

    public function syncProviders(array $linkedProviders): SyncProviders
    {
        return new SyncProviders(linkedProviders: $linkedProviders);
    }
}
