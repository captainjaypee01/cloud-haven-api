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
        try {
            
            $options = new AuthenticateRequestOptions(
                secretKey: config('services.clerk.secret_key'),
                authorizedParties: [
                    config('services.clerk.domain'), // e.g. 'your-app.clerk.accounts.dev'
                    ...config('services.clerk.authorized_origins')
                ]
            );

            $requestState = AuthenticateRequest::authenticateRequest($request, $options);

            if (!$requestState->isSignedIn()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return $next($request);
        } catch (\Exception $e) {
            Log::error('Clerk authentication failed: ' . $e->getMessage());
            return response()->json(['error' => 'Authentication failed'], 401);
        }
    }
}
