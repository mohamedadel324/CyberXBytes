<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class DdosProtection
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
        
        // Create a unique key for this IP
        $key = 'ddos_protection_' . md5($ip);
        
        // Check if this IP is already blocked (for persistent offenders)
        if (Cache::has('blocked_' . $key)) {
            return response('Too Many Requests', 429);
        }
        
        // Get configuration values
        $maxRequests = Config::get('ddos.web.max_requests', 60);
        $decayMinutes = Config::get('ddos.web.decay_minutes', 1);
        $blockMinutes = Config::get('ddos.web.block_minutes', 10);
        $blockThreshold = Config::get('ddos.web.block_threshold', 120);
        
        // Use Laravel's rate limiter for sophisticated rate limiting
        if (RateLimiter::tooManyAttempts($key, $maxRequests)) {
            // If IP exceeds limits repeatedly, block for longer
            if (RateLimiter::attempts($key) > $blockThreshold) {
                Cache::put('blocked_' . $key, true, now()->addMinutes($blockMinutes));
            }
            
            $seconds = RateLimiter::availableIn($key);
            
            return response('Too Many Requests', 429)
                ->header('Retry-After', $seconds)
                ->header('X-RateLimit-Reset', now()->addSeconds($seconds)->getTimestamp());
        }
        
        RateLimiter::hit($key, $decayMinutes * 60); // Remember for decay_minutes
        
        return $next($request);
    }
} 