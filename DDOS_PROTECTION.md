# DDoS Protection for Laravel

This document outlines the DDoS protection implementation in this Laravel application.

## Overview

The application includes a comprehensive DDoS protection system that:

1. Limits request rates for both web and API routes
2. Blocks persistent offenders temporarily
3. Provides endpoint-specific rate limiting for API routes
4. Allows for IP whitelisting
5. Is fully configurable via environment variables

## Components

### Middleware

Two middleware classes handle the DDoS protection:

1. `DdosProtection` - Applied globally to all routes
2. `ApiDdosProtection` - Specific to API routes with stricter limits

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
| `DDOS_API_MAX_REQUESTS` | 30 | Maximum API requests per minute |
| `DDOS_API_DECAY_MINUTES` | 1 | Time window for API requests |
| `DDOS_API_BLOCK_MINUTES` | 15 | Block time for API offenders |
| `DDOS_API_BLOCK_THRESHOLD` | 60 | Threshold for blocking API offenders |
| `DDOS_API_ENDPOINT_MAX_REQUESTS` | 15 | Maximum requests per minute per API endpoint |
| `DDOS_API_ENDPOINT_DECAY_MINUTES` | 1 | Time window for API endpoint requests |

## IP Whitelisting

You can whitelist IPs in the `config/ddos.php` file:

```php
'whitelist' => [
    '192.168.1.1',
    // Add more IPs here
],
```

## How It Works

1. Each request is checked against rate limits
2. If a client exceeds the rate limit, they receive a 429 (Too Many Requests) response
3. Persistent offenders are temporarily blocked
4. API routes have stricter limits and endpoint-specific limits

## Customization

You can customize the DDoS protection by:

1. Editing the configuration in `config/ddos.php`
2. Setting environment variables in `.env`
3. Modifying the middleware classes for more advanced logic