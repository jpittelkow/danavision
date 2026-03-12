<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeprecateRoute
{
    /**
     * Add deprecation headers to the response (RFC 8594).
     *
     * Usage: ->middleware('deprecate:/api/user/notification-settings')
     */
    public function handle(Request $request, Closure $next, string $successor = ''): Response
    {
        $response = $next($request);

        $response->headers->set('Deprecation', 'true');
        $sunsetDate = config('app.deprecated_route_sunset', '2026-06-01');
        $response->headers->set('Sunset', $sunsetDate);

        if ($successor) {
            $response->headers->set('Link', "<{$successor}>; rel=\"successor-version\"");
        }

        return $response;
    }
}
