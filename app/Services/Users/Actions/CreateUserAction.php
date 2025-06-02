<?php
namespace App\Services\Users\Actions;

use App\Contracts\Users\CreateUserContract;
use App\DTO\Users\NewUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateUserAction implements CreateUserContract
{
    public function handle(NewUser $dto): User
    {
        return DB::transaction(
            fn() => User::updateOrCreate(
                ['clerk_id' => $dto->clerk_id],
                [
                    'email'             => $dto->email,
                    'role'              => $dto->role,
                    'first_name'        => $dto->first_name,
                    'last_name'         => $dto->last_name,
                    'password'          => $dto->password,
                    'image'             => $dto->image_url,
                    'country_code'      => $dto->country_code,
                    'contact_number'    => $dto->contact_number,
                    'email_verified_at' => $dto->email_verified_at->toString(),
                ]
            )
        );
    }
}
