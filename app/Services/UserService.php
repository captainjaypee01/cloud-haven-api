<?php

namespace App\Services;

use App\Models\User;
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
        $userData = [
            'clerk_id'  => $data['id'],
            'email'  => $data['email_addresses'][0]['email_address'],
            'first_name'  => $data['first_name'],
            'last_name'  => $data['last_name'],
            'image'  => $data['image_url'],
            'password' => Hash::make(Str::random(10)),
        ];
        $user = User::create($userData);
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
