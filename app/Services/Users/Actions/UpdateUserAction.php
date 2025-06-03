<?php
namespace App\Services\Users\Actions;

use App\Contracts\Users\UpdateUserContract;
use App\DTO\Users\UpdateUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateUserAction implements UpdateUserContract
{
    public function handle(User $user, UpdateUser $dto): User
    {
        return DB::transaction(fn() => tap($user)->update([
            'clerk_id'          => $dto->clerk_id,
            'email'             => $dto->email ?? $user->email,
            'role'              => $dto->role ?? $user->role,
            'first_name'        => $dto->first_name ?? $user->first_name,
            'last_name'         => $dto->last_name ?? $user->last_name,
            'password'          => $dto->password ?? $user->password,
            'image'             => $dto->image_url ?? $user->image,
            'country_code'      => $dto->country_code ?? $user->country_code,
            'contact_number'    => $dto->contact_number ?? $user->contact_number,
            'email_verified_at' => $dto->email_verified_at ? $dto->email_verified_at->toString() : $user->email_verified_at,
        ]));
    }
}
