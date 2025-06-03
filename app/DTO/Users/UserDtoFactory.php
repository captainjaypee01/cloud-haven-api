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

        return new NewUser(
            clerk_id: $data['id'] ?? null,
            role: $data['role'],
            email: $this->getPrimaryEmail($data),
            first_name: $data['first_name'] ?? '',
            last_name: $data['last_name'] ?? '',
            password: $data['password'] ?? null,
            country_code: $data['country_code'] ?? null,
            contact_number: $data['contact_number'] ?? null,
            image_url: $data['image_url'] ?? null,
            email_verified_at: $data['email_verified_at'] ?? null,
            linkedProviders: $this->getLinkedProviders($data)
        );
    }

    public function updateUser(array $data): UpdateUser
    {
        return new UpdateUser(
            clerk_id: $data['id'] ?? null,
            role: $data['role'] ?? null,
            email: $this->getPrimaryEmail($data) ?? null,
            first_name: $data['first_name'] ?? null,
            last_name: $data['last_name'] ?? null,
            password: $data['password'] ?? null,
            country_code: $data['country_code'] ?? null,
            contact_number: $data['contact_number'] ?? null,
            image_url: $data['image_url'] ?? null,
            email_verified_at: $data['email_verified_at'] ?? null,
            linkedProviders: $this->getLinkedProviders($data)
        );
    }

    public function syncProviders(array $linkedProviders): SyncProviders
    {
        return new SyncProviders(linkedProviders: $linkedProviders);
    }

    protected function getPrimaryEmail(array $data): string
    {
        return $data['email'] ?? ($data['email_addresses'][0]['email_address'] ?? null);
    }

    protected function getLinkedProviders(array $data): array
    {
        return $data['linkedProviders']
            ?? ($data['email_addresses'][0]['linked_to'] ?? []);
    }
}
