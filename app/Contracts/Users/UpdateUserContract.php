<?php

namespace App\Contracts\Users;

use App\DTO\Users\UpdateUser;
use App\Models\User;

interface UpdateUserContract
{
    public function handle(User $user, UpdateUser $dto): User;
}
