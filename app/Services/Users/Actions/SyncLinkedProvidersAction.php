<?php
namespace App\Services\Users\Actions;

use App\Contracts\Users\SyncLinkedProvidersContract;
use App\DTO\Users\SyncProviders;
use App\Models\User;
use App\Models\UserProvider;
use Illuminate\Support\Facades\DB;

final class SyncLinkedProvidersAction implements SyncLinkedProvidersContract
{
    public function handle(User $user, SyncProviders $dto): void
    {
        DB::transaction(function () use ($user, $dto) {
            $currentProviderIds = [];

            foreach ($dto->linkedProviders as $provider) {
                // e.g. $provider['type']='oauth_google', $provider['id']='google‐user‐id'
                $providerName = str_replace('oauth_', '', $provider['type']);
                $providerId   = $provider['id'];

                UserProvider::updateOrCreate(
                    [
                        'user_id'     => $user->id,
                        'provider'    => $providerName,
                        'provider_id' => $providerId,
                    ],
                    [] // no extra columns to fill
                );

                $currentProviderIds[] = $providerId;
            }

            // Delete any stale provider links
            UserProvider::where('user_id', $user->id)
                ->whereNotIn('provider_id', $currentProviderIds)
                ->delete();
        });
    }
}
