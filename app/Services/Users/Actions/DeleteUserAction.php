<?php

namespace App\Services\Users\Actions;

use App\Contracts\Users\DeleteUserContract;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteUserAction implements DeleteUserContract
{
    public function handle(User $user): void
    {
        // Soft‐archive (no hard delete) 
        DB::transaction(fn () => $user->delete());
    }
}
