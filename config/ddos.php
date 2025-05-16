<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DDoS Protection Settings
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for DDoS protection in the application.
    |
    */

    // General web request rate limits
    'web' => [
        'max_requests' => env('DDOS_WEB_MAX_REQUESTS', 60), // Max requests per minute
        'decay_minutes' => env('DDOS_WEB_DECAY_MINUTES', 1), // Time window in minutes
        'block_minutes' => env('DDOS_WEB_BLOCK_MINUTES', 10), // Block time for repeat offenders
        'block_threshold' => env('DDOS_WEB_BLOCK_THRESHOLD', 120), // Requests that trigger blocking
    ],
    
    // API request rate limits (less restrictive now)
    'api' => [
        'max_requests' => env('DDOS_API_MAX_REQUESTS', 60), // Increased from 30 to 60
        'decay_minutes' => env('DDOS_API_DECAY_MINUTES', 1), // Time window in minutes
        'block_minutes' => env('DDOS_API_BLOCK_MINUTES', 15), // Block time for repeat offenders
        'block_threshold' => env('DDOS_API_BLOCK_THRESHOLD', 120), // Increased from 60 to 120
        
        // Per-endpoint limits
        'endpoint_max_requests' => env('DDOS_API_ENDPOINT_MAX_REQUESTS', 30), // Increased from 15 to 30
        'endpoint_decay_minutes' => env('DDOS_API_ENDPOINT_DECAY_MINUTES', 1), // Time window in minutes
    ],
    
    // Special endpoints that need higher limits or no rate limiting
    'excluded_paths' => [
        // User profile endpoints - these often take longer and need higher limits
        'api/user/profile',
        'api/users/profile',
        'api/profile',
        'api/auth/user',
    ],
    
    // Whitelist IPs that should bypass DDoS protection
    'whitelist' => [
        // Add trusted IPs here, e.g. your office IP
        // '192.168.1.1',
    ],
    
    // Enable or disable DDoS protection
    'enabled' => env('DDOS_PROTECTION_ENABLED', true),
]; 