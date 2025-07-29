<?php

namespace App\Contracts\Users;

use App\Models\User;

interface DeleteClerkUserContract
{
    public function handle(User $user): void;
}
