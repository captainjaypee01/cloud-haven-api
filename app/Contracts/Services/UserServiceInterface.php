<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserServiceInterface
{
    /**
     * To get the list of ther Users
     * 
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function list(array $filters): LengthAwarePaginator;

    /**
     * To Show a user by user id
     * 
     * @param int $id
     * @return \App\Models\User
     */
    public function show(int $id): User;

    /**
     * To Create a new user account from Clerk
     * 
     * @param array $userData
     * @return \App\Models\User
     */
    public function createUser(array $data): User;

    /**
     * To Create a new user account from Clerk
     * 
     * @param array $userData
     * @return \App\Models\User
     */
    public function createUserByClerk(array $data): User;

    /**
     * To Update the User by Id
     * 
     * @param int $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function updateById(int $id, array $data): User;

    /**
     * To Update the User by Clerk Id
     * 
     * @param string $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function updateByClerkId(string $id, array $data): User;

    /**
     * To Delete the User by Id
     * 
     * @param int $id
     * @param array $userData
     * @return int
     */
    public function deleteById(int $id): void;

    /**
     * To Delete the User by Clerk Id
     * 
     * @param string $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function deleteByClerkId(string $id): void;

    /**
     * To Update the user's linked providers by User Id
     * 
     * @param string $id
     * @param array $linkedProviders
     * @return mixed
     */
    public function updateUserLinkedProviders($userId, $linkedProviders): void;
}
