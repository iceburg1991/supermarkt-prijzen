<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Albert Heijn Scraper Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Albert Heijn supermarket scraper.
    | Requires OAuth authentication.
    |
    */

    'ah' => [
        'base_url' => env('SCRAPER_AH_BASE_URL', 'https://api.ah.nl'),
        'oauth_url' => env('SCRAPER_AH_OAUTH_URL', 'https://api.ah.nl/mobile-auth/v1/auth'),
        'client_id' => env('SCRAPER_AH_CLIENT_ID', 'appie-ios'),
        'refresh_token' => env('SCRAPER_AH_REFRESH_TOKEN'),
        'rate_limit_delay' => env('SCRAPER_AH_RATE_LIMIT_DELAY', 600), // milliseconds
        'headers' => [
            'User-Agent' => 'Appie/9.28 (iPhone17,3; iPhone; CPU OS 26_1 like Mac OS X)',
            'x-client-name' => 'appie-ios',
            'x-client-version' => '9.28',
            'x-application' => 'AHWEBSHOP',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Jumbo Scraper Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Jumbo supermarket scraper.
    | No authentication required.
    |
    */

    'jumbo' => [
        'base_url' => env('SCRAPER_JUMBO_BASE_URL', 'https://mobileapi.jumbo.com/v17'),
        'rate_limit_delay' => env('SCRAPER_JUMBO_RATE_LIMIT_DELAY', 600), // milliseconds
        'headers' => [
            'User-Agent' => 'Jumbo/13.0.0 (iPhone; iOS 17.0; Scale/3.00)',
            'Accept' => 'application/json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | General Scraper Settings
    |--------------------------------------------------------------------------
    |
    | General configuration applicable to all scrapers.
    |
    */

    'max_retries' => env('SCRAPER_MAX_RETRIES', 3),
    'timeout' => env('SCRAPER_TIMEOUT', 10), // seconds
    'max_results' => env('SCRAPER_MAX_RESULTS', 1000),
    'debug' => env('SCRAPER_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for scheduled scraping tasks.
    |
    */

    'schedule' => [
        'enabled' => env('SCRAPER_SCHEDULE_ENABLED', true),
        'time' => env('SCRAPER_SCHEDULE_TIME', '02:00'),
        'timezone' => env('SCRAPER_SCHEDULE_TIMEZONE', 'Europe/Amsterdam'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for scraper notifications.
    |
    */

    'notifications' => [
        'enabled' => env('SCRAPER_NOTIFICATIONS_ENABLED', true),
        'channels' => [
            'mail' => env('SCRAPER_NOTIFY_MAIL', false),
            'slack' => env('SCRAPER_NOTIFY_SLACK', false),
        ],
        'mail_to' => env('SCRAPER_NOTIFY_MAIL_TO'),
        'slack_webhook' => env('SCRAPER_NOTIFY_SLACK_WEBHOOK'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for analytics and caching.
    |
    */

    'analytics' => [
        'cache_enabled' => env('SCRAPER_ANALYTICS_CACHE_ENABLED', true),
        'cache_ttl' => env('SCRAPER_ANALYTICS_CACHE_TTL', 3600), // seconds (1 hour)
        'performance_threshold' => env('SCRAPER_ANALYTICS_PERFORMANCE_THRESHOLD', 1000), // milliseconds
    ],

];
