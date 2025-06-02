<?php

namespace App\Contracts\Users;

use App\Models\User;

interface DeleteUserContract
{
    public function handle(User $user): void;
}
