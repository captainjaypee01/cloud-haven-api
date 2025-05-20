<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProvider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{

    /**
     * To Create a new user account from Clerk
     * 
     * @param array $userData
     * @return \App\Models\User
     */
    public function createUserByClerk(array $data): User
    {
        // Find or create the user using Clerk's ID
        $user = User::updateOrCreate(
            ['clerk_id' => $data['id']],
            [
                'email' => $data['email_addresses'][0]['email_address'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'image' => $data['image_url'],
            ]
        );

        // Extract providers from the webhook's linked_to array
        $linkedProviders = $data['email_addresses'][0]['linked_to'] ?? [];

        $this->updateUserLinkedProviders($user->clerk_id, $linkedProviders);

        return $user;
    }

    /**
     * To Update the User by Id
     * 
     * @param int $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function updateById(int $id, array $data): User
    {
        $userData = [
            'clerk_id'  => $data['id'],
            'email'  => $data['email_addresses'][0]['email_address'],
            'first_name'  => $data['first_name'],
            'last_name'  => $data['last_name'],
            'image'  => $data['image_url'],
        ];
        $user = User::find($id)->update($userData);
        return $user;
    }

    /**
     * To Update the User by Clerk Id
     * 
     * @param string $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function updateByClerkId($id, array $data): User
    {
        // Find or create the user using Clerk's ID
        $user = User::updateOrCreate(
            ['clerk_id' => $data['id']],
            [
                'email' => $data['email_addresses'][0]['email_address'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'image' => $data['image_url'],
            ]
        );

        // Extract providers from the webhook's linked_to array
        $linkedProviders = $data['email_addresses'][0]['linked_to'] ?? [];

        $this->updateUserLinkedProviders($user->clerk_id, $linkedProviders);
        
        return $user;
    }

    /**
     * To Delete the User by Id
     * 
     * @param int $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function deleteById($id): User
    {
        $user = User::find($id)->delete();
        return $user;
    }

    /**
     * To Delete the User by Clerk Id
     * 
     * @param string $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function deleteByClerkId($id): void
    {
        User::where('clerk_id', $id)->delete();
    }

    /**
     * To Update the user's linked providers by User Id
     * 
     * @param string $id
     * @param array $linkedProviders
     * @return mixed
     */
    public function updateUserLinkedProviders($userId, $linkedProviders)
    {
        // Track provider IDs to delete stale entries
        $currentProviderIds = [];

        foreach ($linkedProviders as $provider) {
            $providerName = str_replace('oauth_', '', $provider['type']); // "google", "facebook"
            $providerId = $provider['id'];

            // Update or create the provider link
            UserProvider::updateOrCreate(
                [
                    'user_id' => $userId,
                    'provider' => $providerName,
                    'provider_id' => $providerId,
                ]
            );

            $currentProviderIds[] = $providerId;
        }

        // Delete providers no longer linked
        UserProvider::where('user_id', $userId)
            ->whereNotIn('provider_id', $currentProviderIds)
            ->delete();
    }
}
