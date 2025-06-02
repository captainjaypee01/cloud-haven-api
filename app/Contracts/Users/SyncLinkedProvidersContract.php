<?php

namespace App\Contracts\Users;

use App\DTO\Users\SyncProviders;
use App\Models\User;

interface SyncLinkedProvidersContract
{
    /**
     * Given a User entity and a SyncProviders DTO, ensure that
     * the `user_providers` table is updated to reflect exactly those providers.
     */
    public function handle(User $user, SyncProviders $dto): void;
}
