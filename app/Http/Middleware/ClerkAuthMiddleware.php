<?php

namespace App\Http\Middleware;

use Clerk\Backend\ClerkBackend;
use Clerk\Backend\ClerkBackendBuilder;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequest;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequestOptions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ClerkAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $options = new AuthenticateRequestOptions(
            secretKey: getenv("CLERK_SECRET_KEY"),
            authorizedParties: ["localhost", "localhost:5173", "localhost:3000"]
        );

        $requestState = AuthenticateRequest::authenticateRequest($request, $options);

        $isSignedIn = $requestState->isSignedIn();

        Log::info([$isSignedIn]);

        return $next($request);
    }
}
