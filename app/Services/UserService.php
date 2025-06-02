<?php

namespace App\Services;

use App\Contracts\Services\UserServiceInterface;
use App\Contracts\Users\CreateUserContract;
use App\Contracts\Users\DeleteUserContract;
use App\Contracts\Users\UpdateUserContract;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Users\SyncLinkedProvidersContract;
use App\DTO\Users\UserDtoFactory;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService implements UserServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface     $repository,
        private   CreateUserContract          $creator,
        private   UpdateUserContract          $updater,
        private   DeleteUserContract          $deleter,
        private   SyncLinkedProvidersContract $syncProviders,
        private   UserDtoFactory              $dtoFactory,
    ) {}

    /**
     * To get the list of ther Users
     * 
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }
    
    /**
     * To Show a user by user id
     * 
     * @param int $id
     * @return \App\Models\User
     */
    public function show(int $id): User
    {
        return $this->repository->getById($id);
    }

    /**
     * To Create a new user account from Clerk
     * 
     * @param array $userData
     * @return \App\Models\User
     */
    public function createUser(array $data): User
    {
        // Find or create the user using Clerk's ID
        $user = User::updateOrCreate(
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
     * To Create a new user account from Clerk
     * 
     * @param array $userData
     * @return \App\Models\User
     */
    public function createUserByClerk(array $data): User
    {
        
        $dto  = $this->dtoFactory->newUser($data);
        $user = $this->creator->handle($dto);

        // Run linked‐providers sync
        $syncDto = $this->dtoFactory->syncProviders($dto->linkedProviders);
        $this->syncProviders->handle($user, $syncDto);

        return $user;
        // // Find or create the user using Clerk's ID
        // $user = User::updateOrCreate(
        //     ['clerk_id' => $data['id']],
        //     [
        //         'email' => $data['email_addresses'][0]['email_address'],
        //         'first_name' => $data['first_name'],
        //         'last_name' => $data['last_name'],
        //         'image' => $data['image_url'],
        //     ]
        // );

        // // Extract providers from the webhook's linked_to array
        // $linkedProviders = $data['email_addresses'][0]['linked_to'] ?? [];

        // $this->updateUserLinkedProviders($user->clerk_id, $linkedProviders);

        // return $user;
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
        $user = $this->repository->getById($id);
        $dto  = $this->dtoFactory->updateUser($data);
        $updatedUser = $this->updater->handle($user, $dto);
        return $updatedUser;
        // $userData = [
        //     'clerk_id'  => $data['clerk_id'],
        //     'email'  => $data['email_addresses'][0]['email_address'],
        //     'first_name'  => $data['first_name'],
        //     'last_name'  => $data['last_name'],
        //     'image'  => $data['image_url'],
        // ];

        // $user = User::find($id);
        // $user->update($userData);
        // $freshUser = $user->refresh();
        // return $freshUser;
    }

    /**
     * To Update the User by Clerk Id
     * 
     * @param string $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function updateByClerkId(string $clerkId, array $data): User
    {
        
        // If user exists with that clerk_id, pull it; otherwise the
        // CreateUserAction will insert it.
        $existing = $this->repository->findByClerkId($clerkId);
        $dto      = $this->dtoFactory->updateUser($data);

        if ($existing) {
            $user = $this->updater->handle($existing, $dto);
        } else {
            $user = $this->creator->handle($this->dtoFactory->newUser($data));
        }

        // Sync providers
        $emailEntry   = $data['email_addresses'][0] ?? [];
        $linkedTo     = $emailEntry['linked_to'] ?? [];
        $syncDto      = $this->dtoFactory->syncProviders($linkedTo);
        $this->syncProviders->handle($user, $syncDto);

        return $user;
        // // Find or create the user using Clerk's ID
        // $user = User::updateOrCreate(
        //     ['clerk_id' => $data['id']],
        //     [
        //         'email' => $data['email_addresses'][0]['email_address'],
        //         'first_name' => $data['first_name'],
        //         'last_name' => $data['last_name'],
        //         'image' => $data['image_url'],
        //     ]
        // );

        // // Extract providers from the webhook's linked_to array
        // $linkedProviders = $data['email_addresses'][0]['linked_to'] ?? [];

        // $this->updateUserLinkedProviders($user->clerk_id, $linkedProviders);
        
        // return $user;
    }

    /**
     * To Delete the User by Id
     * 
     * @param int $id
     * @param array $userData
     * @return int
     */
    public function deleteById($id): void
    {
        $user = $this->repository->getById($id);
        $this->deleter->handle($user);
    }

    /**
     * To Delete the User by Clerk Id
     * 
     * @param string $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function deleteByClerkId(string $clerkId): void
    {
        $user = $this->repository->findByClerkId($clerkId);
        if ($user) {
            $this->deleter->handle($user);
        }
    }

    /**
     * To Update the user's linked providers by User Id
     * 
     * @param string $id
     * @param array $linkedProviders
     * @return mixed
     */
    public function updateUserLinkedProviders($userId, $linkedProviders): void
    {

        $user = $this->repository->getById($userId);
        $syncDto = $this->dtoFactory->syncProviders($linkedProviders);
        $this->syncProviders->handle($user, $syncDto);

        // Track provider IDs to delete stale entries
        // $currentProviderIds = [];
        // foreach ($linkedProviders as $provider) {
        //     $providerName = str_replace('oauth_', '', $provider['type']); // "google", "facebook"
        //     $providerId = $provider['id'];

        //     // Update or create the provider link
        //     UserProvider::updateOrCreate(
        //         [
        //             'user_id' => $userId,
        //             'provider' => $providerName,
        //             'provider_id' => $providerId,
        //         ]
        //     );

        //     $currentProviderIds[] = $providerId;
        // }

        // // Delete providers no longer linked
        // UserProvider::where('user_id', $userId)
        //     ->whereNotIn('provider_id', $currentProviderIds)
        //     ->delete();
    }
}
