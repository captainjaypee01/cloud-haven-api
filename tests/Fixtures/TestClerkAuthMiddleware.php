<?php

namespace Tests\Fixtures;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TestClerkAuthMiddleware
{
    /**
     * Instead of validating a real Clerk JWT, this middleware
     * simply “logs in” a user whose ID we pass in a special header:
     *   X-TEST-USER-ID: <local user id>
     * If that header is missing or points to a nonexistent user, we
     * return 401. Otherwise, we do Auth::login($user).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $testUserId = $request->header('X-TEST-USER-ID');
        if (! $testUserId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = User::find($testUserId);
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Auth::login($user);

        return $next($request);
    }
}
