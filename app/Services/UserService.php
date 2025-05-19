<?php

namespace App\Services;

use App\Models\User;

class UserService
{
    
    /**
     * To Create a new user account
     * 
     * @param array $userData
     * @return \App\Models\User
     */
    public function store(array $userData): User
    {
        $user = User::insert($userData);
        return $user;
    }

    /**
     * To Update the User by Id
     * 
     * @param int $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function updateById(int $id, array $userData): User
    {
        $user = User::find($id)->update($userData);
        return $user;
    }

    /**
     * To Update the User by Clerk Id
     * 
     * @param int $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function updateByClerkId($id, array $userData): User
    {
        $user = User::where('clerk_id', $id)->update($userData);
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
     * @param int $id
     * @param array $userData
     * @return \App\Models\User
     */
    public function deleteByClerkId($id): void
    {
        User::where('clerk_id', $id)->delete();
    }

}
