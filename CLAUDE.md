# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This repository contains an enterprise-grade Laravel package (`Supabase\LaravelAuth`) that completely replaces Laravel's native authentication system with Supabase authentication. The package is located in `packages/laravel-supabase-auth/` and provides comprehensive authentication functionality with advanced enterprise features including circuit breakers, rate limiting, caching, monitoring, and security hardening.

## Development Commands

### Package Testing
```bash
cd packages/laravel-supabase-auth
composer install
vendor/bin/phpunit
```

### Code Quality and Analysis
```bash
# Run PHPStan analysis
vendor/bin/phpstan analyse

# Format code with PHP CS Fixer
vendor/bin/php-cs-fixer fix

# Run all quality checks
composer analyse && composer format && composer test
```

### Run specific test suites
```bash
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
```

### Enterprise Management Commands
```bash
# Validate configuration and test connection
php artisan supabase:validate-config --show-summary --test-connection

# Test Supabase connection with detailed output
php artisan supabase:test-connection --detailed --reset-circuit-breaker

# Clear authentication cache
php artisan supabase:clear-cache --type=all --stats
```

## Architecture Overview

### Enterprise Authentication Flow
The package implements a robust, enterprise-ready authentication system with multiple layers of resilience and security:

1. **Circuit Breaker Pattern** - `Services/CircuitBreaker.php` prevents cascade failures by monitoring API health and temporarily blocking requests when failure thresholds are exceeded
2. **Rate Limiting** - `Services/RateLimiter.php` provides sophisticated rate limiting with progressive delays and operation-specific limits
3. **Caching Layer** - `Services/CacheManager.php` implements intelligent caching with compression, TTL management, and cache warming
4. **Configuration Validation** - `Services/ConfigurationValidator.php` ensures all required settings are present and valid for the target environment

### Core Authentication Components
- **SupabaseGuard** (`Guards/SupabaseGuard.php`) - Enhanced authentication guard with logging, error handling, and automatic token refresh
- **SupabaseUserProvider** (`Providers/SupabaseUserProvider.php`) - User provider with comprehensive logging and error handling  
- **SupabaseAuth** (`Services/SupabaseAuth.php`) - Main service implementing the `SupabaseAuthInterface` contract with caching and circuit breaker integration
- **SupabaseClient** (`Services/SupabaseClient.php`) - HTTP client with retry logic, connection pooling, and comprehensive error handling

### Enterprise Service Integration
The `SupabaseAuthServiceProvider` registers all services with proper dependency injection, validates configuration on boot, and provides extensive customization options. Services are bound to contracts for easy testing and extension.

### Security and Resilience Features

#### Circuit Breaker Implementation
- Monitors API failure rates and automatically opens circuit when thresholds are exceeded
- Implements half-open state for gradual recovery testing
- Configurable failure thresholds, recovery timeouts, and exception types
- Comprehensive logging and metrics for monitoring

#### Rate Limiting System
- Operation-specific rate limits (login, register, password reset, OTP)
- Progressive delay implementation for repeated attempts
- Redis-backed with automatic cleanup and expiration
- Detailed attempt tracking and retry-after headers

#### Caching Architecture
- Multi-layer caching for user data, JWT validation, OAuth state, and API responses
- Optional compression for large data sets
- Cache warming and intelligent invalidation
- Performance metrics and hit/miss tracking

#### Configuration Management
- Environment-specific configurations (production, staging, development)
- Comprehensive validation with specific error messages
- Security policy enforcement (HTTPS, secure cookies, SSL verification)
- Connection testing and health checks

### Monitoring and Observability
- Structured logging with configurable levels and sensitive data filtering
- Metrics collection with Prometheus support
- Health check endpoints for service monitoring
- Circuit breaker and rate limiting status tracking

### Error Handling and Exceptions
Custom exception hierarchy provides specific error types:
- `ConfigurationException` - Configuration validation errors
- `AuthenticationException` - Authentication-specific errors with proper HTTP codes
- `CircuitBreakerException` - Service availability errors

### Console Commands
Enterprise management commands provide operational capabilities:
- `ValidateConfigCommand` - Validates configuration and tests connections
- `TestConnectionCommand` - Comprehensive connection testing with detailed diagnostics
- `ClearCacheCommand` - Intelligent cache management with selective clearing

## Required Environment Variables

### Core Configuration (Required)
```env
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_ANON_KEY=your-anon-key
SUPABASE_SERVICE_KEY=your-service-key
SUPABASE_JWT_SECRET=your-jwt-secret (minimum 32 characters)
```

### Enterprise Features (Optional)
```env
# Circuit Breaker
SUPABASE_CIRCUIT_BREAKER_ENABLED=true
SUPABASE_CB_FAILURE_THRESHOLD=5

# Rate Limiting
SUPABASE_RATE_LIMITING_ENABLED=true
SUPABASE_LOGIN_MAX_ATTEMPTS=5

# Caching
SUPABASE_CACHE_ENABLED=true
SUPABASE_CACHE_STORE=redis

# Security
SUPABASE_SECURE_COOKIES=true
SUPABASE_CSRF_PROTECTION=true
```

## Key Integration Points

### Laravel Integration
The package seamlessly integrates with Laravel through:
- Custom guard and provider registration in Laravel's auth system
- Service provider that validates configuration and binds services
- Facade registration for easy access (`SupabaseAuth::signIn()`)
- Console command registration for operational management

### Database Schema
UUID-based user table compatible with Supabase's auth system, including proper indexes and constraints for performance and data integrity.

### Middleware Chain
Enterprise-grade middleware stack with comprehensive logging, rate limiting, and security checks:
1. `AuthenticateSupabase` - Basic authentication with detailed error responses
2. `EnsureTokenIsValid` - Token validation with automatic refresh and circuit breaker integration
3. `RedirectIfAuthenticated` - Guest-only route protection with configurable redirects

### Contract-Based Design
All major services implement contracts for easier testing, mocking, and extension. The package follows SOLID principles with clear separation of concerns.

### Quality Assurance
- PHPStan level 8 analysis for maximum type safety
- PHP CS Fixer for consistent code formatting
- Comprehensive test suite with both unit and integration tests
- Strict type declarations throughout the codebase