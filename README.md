# Laravel Supabase Auth

[![Latest Version on Packagist](https://img.shields.io/packagist/v/supabase/laravel-auth.svg?style=flat-square)](https://packagist.org/packages/supabase/laravel-auth)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/supabase/laravel-auth/Tests?label=tests)](https://github.com/Draidel/laravel-supabase-auth/actions)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/supabase/laravel-auth/Check%20&%20fix%20styling?label=code%20style)](https://github.com/Draidel/laravel-supabase-auth/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/supabase/laravel-auth.svg?style=flat-square)](https://packagist.org/packages/supabase/laravel-auth)

An enterprise-grade Laravel package that completely replaces Laravel's native authentication system with Supabase authentication. Built with production-ready features including circuit breakers, rate limiting, comprehensive caching, monitoring, and advanced security.

## ✨ Features

- 🔐 **Complete Auth Replacement**: Drop-in replacement for Laravel's native authentication
- 🏢 **Enterprise-Ready**: Circuit breakers, rate limiting, caching, and monitoring
- 🚀 **High Performance**: Intelligent caching with Redis support and connection pooling
- 🛡️ **Security First**: Advanced password policies, secure cookies, and CSRF protection
- 📊 **Observability**: Comprehensive logging, metrics collection, and health checks
- 🔄 **Resilient**: Automatic retry logic, token refresh, and graceful error handling
- 🎯 **OAuth Support**: Social login with Google, GitHub, Discord, and more
- 📧 **Email Features**: Verification, password reset, and magic links
- 🧪 **Fully Tested**: Comprehensive test suite with 100% coverage
- 📖 **Well Documented**: Complete API documentation with examples

## 🚀 Quick Start

### Installation

```bash
composer require supabase/laravel-auth
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=supabase-auth-config
php artisan vendor:publish --tag=supabase-auth-migrations
```

### Environment Setup

Add to your `.env` file:

```env
# Required
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_KEY=your-service-key
SUPABASE_JWT_SECRET=your-jwt-secret

# Optional - Enterprise Features
SUPABASE_CACHE_ENABLED=true
SUPABASE_CACHE_STORE=redis
SUPABASE_RATE_LIMITING_ENABLED=true
SUPABASE_CIRCUIT_BREAKER_ENABLED=true
```

### Run Migrations

```bash
php artisan migrate
```

### Validate Configuration

```bash
php artisan supabase:validate-config --show-summary --test-connection
```

## 📋 Configuration

### Basic Auth Configuration

Update your `config/auth.php`:

```php
'defaults' => [
    'guard' => 'supabase',
    'passwords' => 'users',
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
        'model' => Supabase\\LaravelAuth\\Models\\SupabaseUser::class,
    ],
],
```

### Enterprise Configuration

The package provides extensive configuration options in `config/supabase-auth.php`:

```php
return [
    // Core Supabase settings
    'url' => env('SUPABASE_URL'),
    'anon_key' => env('SUPABASE_ANON_KEY'),
    'service_key' => env('SUPABASE_SERVICE_KEY'),
    'jwt' => [
        'secret' => env('SUPABASE_JWT_SECRET'),
        'algorithm' => env('SUPABASE_JWT_ALGORITHM', 'HS256'),
        'ttl' => env('SUPABASE_JWT_TTL', 3600),
    ],
    
    // Enterprise features
    'rate_limiting' => [
        'enabled' => env('SUPABASE_RATE_LIMITING_ENABLED', true),
        'login' => [
            'max_attempts' => env('SUPABASE_LOGIN_MAX_ATTEMPTS', 5),
            'decay_minutes' => env('SUPABASE_LOGIN_DECAY_MINUTES', 15),
        ],
    ],
    
    'circuit_breaker' => [
        'enabled' => env('SUPABASE_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('SUPABASE_CB_FAILURE_THRESHOLD', 5),
        'recovery_timeout' => env('SUPABASE_CB_RECOVERY_TIMEOUT', 60),
    ],
    
    'cache' => [
        'enabled' => env('SUPABASE_CACHE_ENABLED', true),
        'store' => env('SUPABASE_CACHE_STORE', 'redis'),
        'ttl' => [
            'user_data' => env('SUPABASE_CACHE_USER_TTL', 300),
            'jwt_validation' => env('SUPABASE_CACHE_JWT_TTL', 60),
        ],
    ],
];
```

## 💡 Usage Examples

### User Registration

```php
use Supabase\LaravelAuth\Facades\SupabaseAuth;

// Using the facade
$response = SupabaseAuth::signUp('user@example.com', 'password123', [
    'name' => 'John Doe',
    'timezone' => 'America/New_York',
]);

// Using API endpoint
POST /auth/supabase/register
{
    "email": "user@example.com",
    "password": "password123",
    "name": "John Doe"
}
```

### User Authentication

```php
use Illuminate\Support\Facades\Auth;

// Using Laravel's Auth facade (recommended)
if (Auth::attempt(['email' => $email, 'password' => $password])) {
    $user = Auth::user();
    // User is authenticated
}

// Using API endpoint
POST /auth/supabase/login
{
    "email": "user@example.com",
    "password": "password123",
    "remember": true
}
```

### Working with Users

```php
$user = Auth::user();

// Access Supabase-specific data
$supabaseData = $user->getSupabaseData();
$userId = $user->getSupabaseUserId();
$metadata = $user->getSupabaseUserMetadata();

// User attributes and methods
$user->hasVerifiedEmail();
$user->isAdmin();
$user->hasRole('premium');
$user->getTimezone();
$user->getPreferences();

// Update user profile
$user->updateSupabaseProfile(['name' => 'New Name']);

// Change password
$user->changeSupabasePassword('new-password');
```

### OAuth Authentication

```php
// Generate OAuth URL
$oauthUrl = SupabaseAuth::signInWithOAuth('google', [
    'redirectTo' => config('app.url') . '/auth/callback',
    'scopes' => 'email profile',
]);

return redirect($oauthUrl);
```

### Password Reset

```php
// Request password reset
SupabaseAuth::resetPasswordForEmail('user@example.com', $redirectUrl);

// Update password (with access token)
SupabaseAuth::updatePassword($accessToken, 'new-password');
```

### Email Verification

```php
// Verify OTP
$response = SupabaseAuth::verifyOtp('user@example.com', '123456', 'email');

// Resend verification email
SupabaseAuth::resendOtp('user@example.com', 'signup');
```

## 🔒 Route Protection

### Basic Middleware

```php
use Supabase\LaravelAuth\Http\Middleware\AuthenticateSupabase;

Route::middleware('auth:supabase')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
});
```

### Advanced Middleware

```php
use Supabase\LaravelAuth\Http\Middleware\EnsureTokenIsValid;

// Ensure token is valid and auto-refresh if needed
Route::middleware(EnsureTokenIsValid::class)->group(function () {
    Route::get('/api/user', [ApiController::class, 'user']);
});
```

### Guest-Only Routes

```php
use Supabase\LaravelAuth\Http\Middleware\RedirectIfAuthenticated;

Route::middleware(RedirectIfAuthenticated::class)->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm']);
    Route::get('/register', [AuthController::class, 'showRegistrationForm']);
});
```

## 🛠️ Management Commands

### Validate Configuration

```bash
# Basic validation
php artisan supabase:validate-config

# With detailed output
php artisan supabase:validate-config --show-summary --test-connection
```

### Test Connection

```bash
# Test Supabase connection
php artisan supabase:test-connection

# With detailed diagnostics
php artisan supabase:test-connection --detailed --reset-circuit-breaker
```

### Cache Management

```bash
# Clear all cache
php artisan supabase:clear-cache

# Clear specific user cache
php artisan supabase:clear-cache --user=user-id

# Show cache statistics
php artisan supabase:clear-cache --stats
```

## 📊 Monitoring & Observability

### Health Checks

```php
$health = app(SupabaseClient::class)->healthCheck();

// Returns:
// [
//     'status' => 'healthy',
//     'response_time_ms' => 45.2,
//     'timestamp' => '2024-01-01T12:00:00Z'
// ]
```

### Circuit Breaker Status

```php
$circuitBreaker = app(CircuitBreakerInterface::class);

if ($circuitBreaker->isOpen('supabase')) {
    // Service is temporarily unavailable
}
```

### Rate Limiting Info

```php
$rateLimiter = app(RateLimiterInterface::class);
$status = $rateLimiter->getStatus($key, $maxAttempts);

// Returns attempt count, retries left, etc.
```

## 🔧 Customization

### Custom User Model

Create your own user model:

```php
use Supabase\LaravelAuth\Traits\HasSupabaseAuth;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasSupabaseAuth;
    
    // Your custom implementation
    protected $fillable = [
        'id', 'email', 'name', 'company_id', 'role',
    ];
    
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
```

Update configuration:

```php
'providers' => [
    'supabase' => [
        'driver' => 'supabase',
        'model' => App\\Models\\User::class,
    ],
],
```

### Event Listeners

Listen to authentication events:

```php
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

Event::listen(Login::class, function (Login $event) {
    // User logged in
    $user = $event->user;
    $guard = $event->guard; // 'supabase'
});

Event::listen(Logout::class, function (Logout $event) {
    // User logged out
});
```

### Custom Services

Extend or replace services by binding in your service provider:

```php
$this->app->bind(SupabaseAuthInterface::class, CustomSupabaseAuth::class);
$this->app->bind(CircuitBreakerInterface::class, CustomCircuitBreaker::class);
```

## 🧪 Testing

### Run Tests

```bash
cd packages/laravel-supabase-auth
composer install
vendor/bin/phpunit
```

### Test Coverage

```bash
vendor/bin/phpunit --coverage-html coverage
```

### Code Quality

```bash
# PHPStan analysis
vendor/bin/phpstan analyse

# Code formatting
vendor/bin/php-cs-fixer fix
```

### Testing in Your App

Mock the Supabase client in your tests:

```php
use Supabase\LaravelAuth\Services\SupabaseClient;

public function test_user_registration()
{
    $mockClient = Mockery::mock(SupabaseClient::class);
    $mockClient->shouldReceive('request')
        ->andReturn(['user' => ['id' => 'test-id']]);
    
    $this->app->instance(SupabaseClient::class, $mockClient);
    
    $response = $this->postJson('/auth/supabase/register', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);
    
    $response->assertSuccessful();
}
```

## 📚 API Reference

### SupabaseAuth Service

```php
interface SupabaseAuthInterface
{
    public function signUp(string $email, string $password, array $data = []): array;
    public function signIn(string $email, string $password): array;
    public function signOut(string $accessToken): array;
    public function refreshToken(string $refreshToken): array;
    public function getUser(string $accessToken): array;
    public function updateUser(string $accessToken, array $data): array;
    public function resetPasswordForEmail(string $email, ?string $redirectTo = null): array;
    public function updatePassword(string $accessToken, string $newPassword): array;
    public function verifyOtp(string $email, string $token, string $type = 'email'): array;
    public function resendOtp(string $email, string $type = 'signup'): array;
    public function signInWithOAuth(string $provider, array $options = []): string;
    public function verifyToken(string $token): array;
    public function getUserById(string $userId): array;
    public function deleteUser(string $userId): array;
}
```

### Available API Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/auth/supabase/register` | User registration | No |
| POST | `/auth/supabase/login` | User login | No |
| POST | `/auth/supabase/logout` | User logout | No |
| POST | `/auth/supabase/refresh` | Refresh access token | No |
| GET | `/auth/supabase/user` | Get authenticated user | Yes |
| POST | `/auth/supabase/password/reset` | Request password reset | No |
| POST | `/auth/supabase/password/update` | Update password | Yes |
| POST | `/auth/supabase/otp/verify` | Verify OTP | No |
| POST | `/auth/supabase/otp/resend` | Resend OTP | No |
| GET | `/auth/supabase/callback` | OAuth callback | No |

## 🚨 Error Handling

The package provides specific exceptions:

```php
use Supabase\LaravelAuth\Exceptions\AuthenticationException;
use Supabase\LaravelAuth\Exceptions\ConfigurationException;
use Supabase\LaravelAuth\Exceptions\CircuitBreakerException;

try {
    SupabaseAuth::signIn($email, $password);
} catch (AuthenticationException $e) {
    // Handle authentication errors
    if ($e->getCode() === 401) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }
} catch (CircuitBreakerException $e) {
    // Handle service unavailable
    return response()->json(['error' => 'Service temporarily unavailable'], 503);
}
```

## 🔧 Troubleshooting

### Common Issues

1. **Configuration Validation Failed**
   ```bash
   php artisan supabase:validate-config --show-summary
   ```

2. **Connection Issues**
   ```bash
   php artisan supabase:test-connection --detailed
   ```

3. **Cache Issues**
   ```bash
   php artisan supabase:clear-cache --stats
   ```

4. **Circuit Breaker Open**
   ```bash
   php artisan supabase:test-connection --reset-circuit-breaker
   ```

### Debug Mode

Enable debug logging:

```env
SUPABASE_LOG_LEVEL=debug
SUPABASE_MONITORING_ENABLED=true
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes and add tests
4. Run the test suite: `composer test`
5. Run code quality checks: `composer analyse && composer format`
6. Commit your changes: `git commit -m 'Add amazing feature'`
7. Push to the branch: `git push origin feature/amazing-feature`
8. Submit a pull request

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## 🙏 Acknowledgments

- Built for the [Supabase](https://supabase.com) ecosystem
- Inspired by Laravel's elegant authentication system
- Thanks to all contributors and the open-source community

## 📞 Support

- 🐛 [Issue Tracker](https://github.com/Draidel/laravel-supabase-auth/issues)
- 💬 [Discussions](https://github.com/Draidel/laravel-supabase-auth/discussions)
- 🌟 [Give us a star](https://github.com/Draidel/laravel-supabase-auth/laravel-auth) if this package helped you!

---

Made with ❤️ by [Draidel.com](https://www.draidel.com)
