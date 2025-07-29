<?php

namespace App\Contracts\Users;

use App\Models\User;
use Clerk\Backend\Models\Components\User as ClerkUser;

interface UpdateClerkUserContract
{
    public function handle(User $user, array $data): ?ClerkUser;
}
