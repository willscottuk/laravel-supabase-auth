<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Supabase Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Supabase authentication integration
    |
    */
    
    'url' => env('SUPABASE_URL'),
    'anon_key' => env('SUPABASE_ANON_KEY'),
    'service_key' => env('SUPABASE_SERVICE_KEY'),
    
    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for JWT token validation and processing
    |
    */
    
    'jwt' => [
        'secret' => env('SUPABASE_JWT_SECRET'),
        'algorithm' => env('SUPABASE_JWT_ALGORITHM', 'HS256'),
        'leeway' => env('SUPABASE_JWT_LEEWAY', 60), // seconds
        'ttl' => env('SUPABASE_JWT_TTL', 3600), // seconds
        'refresh_ttl' => env('SUPABASE_JWT_REFRESH_TTL', 604800), // 7 days
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Configure authentication behavior and redirects
    |
    */
    
    'auth' => [
        'redirect_url' => env('SUPABASE_AUTH_REDIRECT_URL', config('app.url') . '/auth/callback'),
        'provider_redirect' => env('SUPABASE_AUTH_PROVIDER_REDIRECT', '/dashboard'),
        'logout_redirect' => env('SUPABASE_AUTH_LOGOUT_REDIRECT', '/'),
        'session_timeout' => env('SUPABASE_AUTH_SESSION_TIMEOUT', 3600), // seconds
        'auto_refresh' => env('SUPABASE_AUTH_AUTO_REFRESH', true),
        'remember_duration' => env('SUPABASE_AUTH_REMEMBER_DURATION', 2419200), // 28 days
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related settings and hardening options
    |
    */
    
    'security' => [
        'password_policy' => [
            'min_length' => env('SUPABASE_PASSWORD_MIN_LENGTH', 8),
            'require_uppercase' => env('SUPABASE_PASSWORD_REQUIRE_UPPERCASE', false),
            'require_lowercase' => env('SUPABASE_PASSWORD_REQUIRE_LOWERCASE', false),
            'require_numbers' => env('SUPABASE_PASSWORD_REQUIRE_NUMBERS', false),
            'require_symbols' => env('SUPABASE_PASSWORD_REQUIRE_SYMBOLS', false),
        ],
        'encryption' => [
            'algorithm' => env('SUPABASE_ENCRYPTION_ALGORITHM', 'AES-256-GCM'),
            'key_rotation_days' => env('SUPABASE_KEY_ROTATION_DAYS', 90),
        ],
        'csrf_protection' => env('SUPABASE_CSRF_PROTECTION', true),
        'secure_cookies' => env('SUPABASE_SECURE_COOKIES', true),
        'same_site' => env('SUPABASE_SAME_SITE', 'lax'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for authentication endpoints
    |
    */
    
    'rate_limiting' => [
        'enabled' => env('SUPABASE_RATE_LIMITING_ENABLED', true),
        'login' => [
            'max_attempts' => env('SUPABASE_LOGIN_MAX_ATTEMPTS', 5),
            'decay_minutes' => env('SUPABASE_LOGIN_DECAY_MINUTES', 15),
        ],
        'register' => [
            'max_attempts' => env('SUPABASE_REGISTER_MAX_ATTEMPTS', 3),
            'decay_minutes' => env('SUPABASE_REGISTER_DECAY_MINUTES', 60),
        ],
        'password_reset' => [
            'max_attempts' => env('SUPABASE_PASSWORD_RESET_MAX_ATTEMPTS', 3),
            'decay_minutes' => env('SUPABASE_PASSWORD_RESET_DECAY_MINUTES', 30),
        ],
        'otp' => [
            'max_attempts' => env('SUPABASE_OTP_MAX_ATTEMPTS', 3),
            'decay_minutes' => env('SUPABASE_OTP_DECAY_MINUTES', 5),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Configure circuit breaker for API resilience
    |
    */
    
    'circuit_breaker' => [
        'enabled' => env('SUPABASE_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('SUPABASE_CB_FAILURE_THRESHOLD', 5),
        'recovery_timeout' => env('SUPABASE_CB_RECOVERY_TIMEOUT', 60), // seconds
        'expected_exception_types' => [
            \GuzzleHttp\Exception\ConnectException::class,
            \GuzzleHttp\Exception\RequestException::class,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for performance optimization
    |
    */
    
    'cache' => [
        'enabled' => env('SUPABASE_CACHE_ENABLED', true),
        'store' => env('SUPABASE_CACHE_STORE', 'redis'),
        'ttl' => [
            'user_data' => env('SUPABASE_CACHE_USER_TTL', 300), // 5 minutes
            'jwt_validation' => env('SUPABASE_CACHE_JWT_TTL', 60), // 1 minute
            'oauth_state' => env('SUPABASE_CACHE_OAUTH_TTL', 600), // 10 minutes
        ],
        'prefix' => env('SUPABASE_CACHE_PREFIX', 'supabase_auth'),
        'compression' => env('SUPABASE_CACHE_COMPRESSION', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HTTP client behavior and timeouts
    |
    */
    
    'client' => [
        'timeout' => env('SUPABASE_HTTP_TIMEOUT', 10.0), // seconds
        'connect_timeout' => env('SUPABASE_HTTP_CONNECT_TIMEOUT', 5.0), // seconds
        'retry_attempts' => env('SUPABASE_HTTP_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('SUPABASE_HTTP_RETRY_DELAY', 1000), // milliseconds
        'verify_ssl' => env('SUPABASE_HTTP_VERIFY_SSL', true),
        'user_agent' => env('SUPABASE_HTTP_USER_AGENT', 'Supabase-Laravel-Auth/1.0'),
        'pool_size' => env('SUPABASE_HTTP_POOL_SIZE', 10),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Monitoring and Logging
    |--------------------------------------------------------------------------
    |
    | Configure monitoring, metrics, and logging
    |
    */
    
    'monitoring' => [
        'enabled' => env('SUPABASE_MONITORING_ENABLED', true),
        'metrics' => [
            'enabled' => env('SUPABASE_METRICS_ENABLED', true),
            'driver' => env('SUPABASE_METRICS_DRIVER', 'prometheus'),
            'namespace' => env('SUPABASE_METRICS_NAMESPACE', 'supabase_auth'),
        ],
        'logging' => [
            'channel' => env('SUPABASE_LOG_CHANNEL', 'stack'),
            'level' => env('SUPABASE_LOG_LEVEL', 'info'),
            'sensitive_fields' => ['password', 'token', 'secret', 'key'],
        ],
        'health_checks' => [
            'enabled' => env('SUPABASE_HEALTH_CHECKS_ENABLED', true),
            'endpoint' => env('SUPABASE_HEALTH_ENDPOINT', '/health/supabase'),
            'interval' => env('SUPABASE_HEALTH_INTERVAL', 30), // seconds
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Default Guards and Providers
    |--------------------------------------------------------------------------
    |
    | Default authentication configuration
    |
    */
    
    'defaults' => [
        'guard' => env('SUPABASE_DEFAULT_GUARD', 'supabase'),
        'provider' => env('SUPABASE_DEFAULT_PROVIDER', 'supabase'),
    ],
    
    'guards' => [
        'supabase' => [
            'driver' => 'supabase',
            'provider' => 'supabase',
        ],
    ],
    
    'providers' => [
        'supabase' => [
            'driver' => 'supabase',
            'model' => env('SUPABASE_USER_MODEL', \Supabase\LaravelAuth\Models\SupabaseUser::class),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Password Reset Configuration
    |--------------------------------------------------------------------------
    |
    | Configure password reset behavior
    |
    */
    
    'password_reset' => [
        'redirect_url' => env('SUPABASE_PASSWORD_RESET_URL', config('app.url') . '/password/reset'),
        'token_ttl' => env('SUPABASE_PASSWORD_RESET_TTL', 3600), // 1 hour
        'throttle_minutes' => env('SUPABASE_PASSWORD_RESET_THROTTLE', 60),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Email Verification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure email verification behavior
    |
    */
    
    'email_verification' => [
        'enabled' => env('SUPABASE_EMAIL_VERIFICATION_ENABLED', true),
        'redirect_url' => env('SUPABASE_EMAIL_VERIFICATION_URL', config('app.url') . '/email/verify'),
        'token_ttl' => env('SUPABASE_EMAIL_VERIFICATION_TTL', 86400), // 24 hours
        'auto_verify' => env('SUPABASE_EMAIL_AUTO_VERIFY', false),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | OAuth Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure OAuth providers
    |
    */
    
    'oauth' => [
        'providers' => [
            'google' => [
                'enabled' => env('SUPABASE_OAUTH_GOOGLE_ENABLED', false),
                'client_id' => env('SUPABASE_OAUTH_GOOGLE_CLIENT_ID'),
                'redirect_url' => env('SUPABASE_OAUTH_GOOGLE_REDIRECT'),
            ],
            'github' => [
                'enabled' => env('SUPABASE_OAUTH_GITHUB_ENABLED', false),
                'client_id' => env('SUPABASE_OAUTH_GITHUB_CLIENT_ID'),
                'redirect_url' => env('SUPABASE_OAUTH_GITHUB_REDIRECT'),
            ],
            'discord' => [
                'enabled' => env('SUPABASE_OAUTH_DISCORD_ENABLED', false),
                'client_id' => env('SUPABASE_OAUTH_DISCORD_CLIENT_ID'),
                'redirect_url' => env('SUPABASE_OAUTH_DISCORD_REDIRECT'),
            ],
        ],
        'state_ttl' => env('SUPABASE_OAUTH_STATE_TTL', 600), // 10 minutes
        'pkce_enabled' => env('SUPABASE_OAUTH_PKCE_ENABLED', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Environment Configuration
    |--------------------------------------------------------------------------
    |
    | Environment-specific settings
    |
    */
    
    'environment' => [
        'production' => [
            'force_https' => true,
            'debug' => false,
            'strict_mode' => true,
        ],
        'staging' => [
            'force_https' => true,
            'debug' => true,
            'strict_mode' => true,
        ],
        'development' => [
            'force_https' => false,
            'debug' => true,
            'strict_mode' => false,
        ],
    ],
];