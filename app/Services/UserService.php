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
        return $this->repository->getId($id);
    }

    /**
     * To Show a user by clerk id
     * 
     * @param int $id
     * @return \App\Models\User
     */
    public function showByClerkId(string $id): User
    {
        return $this->repository->findByClerkId($id);
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
        $dto  = $this->dtoFactory->newUser($data);
        $user = $this->creator->handle($dto);

        // Run linked‐providers sync
        $syncDto = $this->dtoFactory->syncProviders($dto->linkedProviders);
        $this->syncProviders->handle($user, $syncDto);

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
        $user = $this->repository->getId($id);
        $dto  = $this->dtoFactory->updateUser($data);
        $updatedUser = $this->updater->handle($user, $dto);
        return $updatedUser;
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

        $user = $this->repository->findByClerkId($clerkId);

        $dto = $this->dtoFactory->updateUser(array_merge($data, ['id' => $clerkId]));

        $updatedUser = $this->updater->handle($user, $dto);

        $syncDto = $this->dtoFactory->syncProviders($dto->linkedProviders);
        $this->syncProviders->handle($updatedUser, $syncDto);

        return $updatedUser;
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
        $user = $this->repository->getId($id);
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

        $user = $this->repository->getId($userId);
        $syncDto = $this->dtoFactory->syncProviders($linkedProviders);
        $this->syncProviders->handle($user, $syncDto);
    }
}
