<?php

namespace App\Contracts\Users;

use App\DTO\Users\NewUser;
use App\Models\User;

interface CreateUserContract
{
    public function handle(NewUser $dto): User;
}
