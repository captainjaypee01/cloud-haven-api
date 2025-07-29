<?php

namespace App\Contracts\Users;

use Clerk\Backend\Models\Components\User as ClerkUser;

interface CreateClerkUserContract
{
    public function handle(array $data): ?ClerkUser;
}
