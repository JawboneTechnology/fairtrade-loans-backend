<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\Exceptions\UnauthorizedException;

class AuthenticateWithToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for token in both header and query string
        $token = $request->bearerToken() ?? $request->query('token');

        if (!$token) {
            throw new UnauthorizedException(403, 'Authentication token required');
        }

        // Authenticate the user
//        if (!auth()->onceUsingId($token)) { // Or your custom token logic
//            throw new UnauthorizedException(403, 'Invalid authentication token');
//        }

        return $next($request);
    }
}
