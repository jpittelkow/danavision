<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddCorrelationId
{
    public const HEADER_NAME = 'X-Correlation-ID';

    /**
     * Handle an incoming request.
     * Generate or propagate a correlation ID for request tracing and add it to the response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header(self::HEADER_NAME);
        if (! is_string($id) || trim($id) === '') {
            $id = Str::uuid()->toString();
        } else {
            $id = trim($id);
        }

        app()->instance('correlation_id', $id);

        $response = $next($request);

        $response->headers->set(self::HEADER_NAME, $id);

        return $response;
    }
}
