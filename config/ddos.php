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
    
    // API request rate limits (stricter)
    'api' => [
        'max_requests' => env('DDOS_API_MAX_REQUESTS', 30), // Max requests per minute
        'decay_minutes' => env('DDOS_API_DECAY_MINUTES', 1), // Time window in minutes
        'block_minutes' => env('DDOS_API_BLOCK_MINUTES', 15), // Block time for repeat offenders
        'block_threshold' => env('DDOS_API_BLOCK_THRESHOLD', 60), // Requests that trigger blocking
        
        // Per-endpoint limits
        'endpoint_max_requests' => env('DDOS_API_ENDPOINT_MAX_REQUESTS', 15), // Max requests per minute per endpoint
        'endpoint_decay_minutes' => env('DDOS_API_ENDPOINT_DECAY_MINUTES', 1), // Time window in minutes
    ],
    
    // Whitelist IPs that should bypass DDoS protection
    'whitelist' => [
        // Add trusted IPs here, e.g. your office IP
        // '192.168.1.1',
    ],
    
    // Enable or disable DDoS protection
    'enabled' => env('DDOS_PROTECTION_ENABLED', true),
]; 