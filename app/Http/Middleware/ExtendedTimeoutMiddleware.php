<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtendedTimeoutMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Increase PHP execution time limit for these requests
        set_time_limit(120); // 2 minutes
        
        // Get the response
        $response = $next($request);
        
        // Set cache control headers to help with timeouts
        if ($response instanceof Response) {
            $response->headers->set('Cache-Control', 'no-cache, private');
            $response->headers->set('X-Extended-Timeout', 'true');
        }
        
        return $response;
    }
} 