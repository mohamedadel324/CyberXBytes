# DDoS Protection for Laravel

This document outlines the DDoS protection implementation in this Laravel application.

## Overview

The application includes a comprehensive DDoS protection system that:

1. Limits request rates for both web and API routes
2. Blocks persistent offenders temporarily
3. Provides endpoint-specific rate limiting for API routes
4. Allows for IP whitelisting
5. Is fully configurable via environment variables
6. Includes special handling for user profile endpoints with extended timeouts

## Components

### Middleware

Three middleware classes handle the DDoS protection:

1. `DdosProtection` - Applied globally to all routes
2. `ApiDdosProtection` - Specific to API routes with optimized limits
3. `ExtendedTimeoutMiddleware` - For profile and other resource-intensive endpoints

### Configuration

The DDoS protection is configurable via:

- Configuration file: `config/ddos.php`
- Environment variables in `.env`

### Console Command

A console command is available to publish the DDoS protection configuration:

```bash
php artisan ddos:publish
```

This will add the required environment variables to your `.env` file.

## Configuration Options

| Environment Variable | Default | Description |
|---------------------|---------|-------------|
| `DDOS_PROTECTION_ENABLED` | true | Enable/disable DDoS protection |
| `DDOS_WEB_MAX_REQUESTS` | 60 | Maximum web requests per minute |
| `DDOS_WEB_DECAY_MINUTES` | 1 | Time window for web requests |
| `DDOS_WEB_BLOCK_MINUTES` | 10 | Block time for web offenders |
| `DDOS_WEB_BLOCK_THRESHOLD` | 120 | Threshold for blocking web offenders |
| `DDOS_API_MAX_REQUESTS` | 60 | Maximum API requests per minute |
| `DDOS_API_DECAY_MINUTES` | 1 | Time window for API requests |
| `DDOS_API_BLOCK_MINUTES` | 15 | Block time for API offenders |
| `DDOS_API_BLOCK_THRESHOLD` | 120 | Threshold for blocking API offenders |
| `DDOS_API_ENDPOINT_MAX_REQUESTS` | 30 | Maximum requests per minute per API endpoint |
| `DDOS_API_ENDPOINT_DECAY_MINUTES` | 1 | Time window for API endpoint requests |

## IP Whitelisting

You can whitelist IPs in the `config/ddos.php` file:

```php
'whitelist' => [
    '192.168.1.1',
    // Add more IPs here
],
```

## Excluded Paths

Some paths are excluded from rate limiting to prevent timeouts for resource-intensive operations:

```php
'excluded_paths' => [
    'api/user/profile',
    'api/users/profile',
    'api/profile',
    'api/auth/user',
    // Add more paths here
],
```

## Extended Timeouts

The application includes special handling for endpoints that may take longer to process:

1. Server-side: `ExtendedTimeoutMiddleware` increases PHP execution time limit
2. Client-side: Axios interceptors increase timeout for profile-related requests

## How It Works

1. Each request is checked against rate limits
2. If a client exceeds the rate limit, they receive a 429 (Too Many Requests) response
3. Persistent offenders are temporarily blocked
4. API routes have optimized limits and endpoint-specific limits
5. Profile and resource-intensive endpoints have extended timeouts and special handling

## Customization

You can customize the DDoS protection by:

1. Editing the configuration in `config/ddos.php`
2. Setting environment variables in `.env`
3. Modifying the middleware classes for more advanced logic
4. Adding more paths to the excluded_paths list for endpoints that need special handling

## Troubleshooting Timeouts

If you're experiencing timeouts:

1. Check if the endpoint is in the excluded_paths list
2. Apply the 'extended-timeout' middleware to the route
3. Increase the client-side Axios timeout for that specific request
4. Consider optimizing the endpoint's database queries and processing