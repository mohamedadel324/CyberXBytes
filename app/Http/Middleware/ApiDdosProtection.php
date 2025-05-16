<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiDdosProtection
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if protection is disabled
        if (!Config::get('ddos.enabled', true)) {
            return $next($request);
        }
        
        // Get client IP address
        $ip = $request->ip();
        
        // Skip for whitelisted IPs
        if (in_array($ip, Config::get('ddos.whitelist', []))) {
            return $next($request);
        }
        
        // Get the current path
        $path = $request->path();
        
        // Skip for excluded paths (like profile endpoints that may take longer)
        $excludedPaths = Config::get('ddos.excluded_paths', []);
        foreach ($excludedPaths as $excludedPath) {
            if (Str::is($excludedPath, $path)) {
                return $next($request);
            }
        }
        
        // Create unique keys for this IP - one for general API and one endpoint-specific
        $generalKey = 'api_ddos_protection_' . md5($ip);
        $endpointKey = 'api_endpoint_' . md5($ip . $path);
        
        // Check if this IP is already blocked
        if (Cache::has('api_blocked_' . $generalKey)) {
            return response()->json(['error' => 'Too Many Requests'], 429);
        }
        
        // Get configuration values
        $maxRequests = Config::get('ddos.api.max_requests', 60);
        $decayMinutes = Config::get('ddos.api.decay_minutes', 1);
        $blockMinutes = Config::get('ddos.api.block_minutes', 15);
        $blockThreshold = Config::get('ddos.api.block_threshold', 120);
        $endpointMaxRequests = Config::get('ddos.api.endpoint_max_requests', 30);
        $endpointDecayMinutes = Config::get('ddos.api.endpoint_decay_minutes', 1);
        
        // General API rate limit
        if (RateLimiter::tooManyAttempts($generalKey, $maxRequests)) {
            // If IP exceeds limits repeatedly, block for longer
            if (RateLimiter::attempts($generalKey) > $blockThreshold) {
                Cache::put('api_blocked_' . $generalKey, true, now()->addMinutes($blockMinutes));
            }
            
            $seconds = RateLimiter::availableIn($generalKey);
            
            return response()->json(['error' => 'Too Many Requests', 'retry_after' => $seconds], 429)
                ->header('Retry-After', $seconds)
                ->header('X-RateLimit-Reset', now()->addSeconds($seconds)->getTimestamp());
        }
        
        // Endpoint-specific rate limit (to prevent targeted attacks)
        if (RateLimiter::tooManyAttempts($endpointKey, $endpointMaxRequests)) {
            $seconds = RateLimiter::availableIn($endpointKey);
            
            return response()->json(['error' => 'Too Many Requests', 'retry_after' => $seconds], 429)
                ->header('Retry-After', $seconds)
                ->header('X-RateLimit-Reset', now()->addSeconds($seconds)->getTimestamp());
        }
        
        // Track both general and endpoint-specific usage
        RateLimiter::hit($generalKey, $decayMinutes * 60);
        RateLimiter::hit($endpointKey, $endpointDecayMinutes * 60);
        
        return $next($request);
    }
} 