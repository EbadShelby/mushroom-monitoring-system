<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDeviceToken
{
    /**
     * Require a valid X-Device-Token header matching config('services.device.token').
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.device.token');
        $providedToken = (string) $request->header('X-Device-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
