<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Supabase\LaravelAuth\Contracts\RateLimiterInterface;
use Supabase\LaravelAuth\Exceptions\AuthenticationException;

class RateLimiter implements RateLimiterInterface
{
    private string $cachePrefix;
    
    public function __construct()
    {
        $this->cachePrefix = config('supabase-auth.cache.prefix', 'supabase_auth') . ':rl';
    }
    
    public function attempt(string $key, int $maxAttempts, int $decayMinutes = 1): bool
    {
        if (!config('supabase-auth.rate_limiting.enabled', true)) {
            return true;
        }
        
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            $this->logRateLimitEvent('Rate limit exceeded', $key, $maxAttempts);
            throw AuthenticationException::rateLimitExceeded($this->availableIn($key));
        }
        
        $this->hit($key, $decayMinutes);
        
        return true;
    }
    
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }
    
    public function availableIn(string $key): int
    {
        $cacheKey = $this->getCacheKey($key);
        $timeKey = $this->getTimeKey($key);
        
        $firstAttemptTime = Cache::get($timeKey);
        
        if (!$firstAttemptTime) {
            return 0;
        }
        
        $decayMinutes = $this->getDecayMinutes($key);
        $availableAt = $firstAttemptTime + ($decayMinutes * 60);
        
        return max(0, $availableAt - time());
    }
    
    public function clear(string $key): void
    {
        Cache::forget($this->getCacheKey($key));
        Cache::forget($this->getTimeKey($key));
        
        $this->logRateLimitEvent('Rate limit cleared', $key);
    }
    
    public function retriesLeft(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }
    
    public function hit(string $key, int $decayMinutes = 1): int
    {
        $cacheKey = $this->getCacheKey($key);
        $timeKey = $this->getTimeKey($key);
        $ttl = $decayMinutes * 60;
        
        $currentAttempts = Cache::get($cacheKey, 0);
        $newAttempts = $currentAttempts + 1;
        
        // Set the first attempt time if this is the first attempt
        if ($currentAttempts === 0) {
            Cache::put($timeKey, time(), $ttl);
        }
        
        Cache::put($cacheKey, $newAttempts, $ttl);
        
        $this->logRateLimitEvent('Rate limit hit recorded', $key, 0, $newAttempts);
        
        return $newAttempts;
    }
    
    public function attempts(string $key): int
    {
        return Cache::get($this->getCacheKey($key), 0);
    }
    
    public function resetAttempts(string $key): bool
    {
        $this->clear($key);
        return true;
    }
    
    /**
     * Get the rate limit configuration for a specific operation
     */
    public function getRateLimitConfig(string $operation): array
    {
        $config = config("supabase-auth.rate_limiting.{$operation}");
        
        if (!$config) {
            // Default rate limit configuration
            return [
                'max_attempts' => 5,
                'decay_minutes' => 15,
            ];
        }
        
        return $config;
    }
    
    /**
     * Apply rate limiting for authentication operations
     */
    public function attemptAuth(string $operation, string $identifier): bool
    {
        $config = $this->getRateLimitConfig($operation);
        $key = $this->generateKey($operation, $identifier);
        
        return $this->attempt($key, $config['max_attempts'], $config['decay_minutes']);
    }
    
    /**
     * Generate a standardized rate limiting key
     */
    public function generateKey(string $operation, string $identifier): string
    {
        return hash('sha256', "{$operation}:{$identifier}");
    }
    
    /**
     * Get comprehensive rate limiting status
     */
    public function getStatus(string $key, int $maxAttempts): array
    {
        $attempts = $this->attempts($key);
        $retriesLeft = $this->retriesLeft($key, $maxAttempts);
        $availableIn = $this->availableIn($key);
        
        return [
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'retries_left' => $retriesLeft,
            'available_in_seconds' => $availableIn,
            'rate_limited' => $this->tooManyAttempts($key, $maxAttempts),
        ];
    }
    
    /**
     * Apply progressive delays for repeated attempts
     */
    public function applyProgressiveDelay(string $key, int $maxAttempts): void
    {
        $attempts = $this->attempts($key);
        
        if ($attempts === 0) {
            return;
        }
        
        // Progressive delay: 1s, 2s, 4s, 8s, 16s...
        $delay = min(pow(2, $attempts - 1), 30); // Cap at 30 seconds
        
        if ($delay > 0) {
            $this->logRateLimitEvent('Applying progressive delay', $key, $maxAttempts, $attempts);
            usleep($delay * 1000000); // Convert to microseconds
        }
    }
    
    /**
     * Clean up expired rate limiting entries
     */
    public function cleanup(): int
    {
        // This would typically be handled by cache TTL, but we can provide
        // additional cleanup logic if needed
        $cleaned = 0;
        
        // Implementation would depend on cache driver capabilities
        // For now, we rely on cache TTL for automatic cleanup
        
        return $cleaned;
    }
    
    private function getCacheKey(string $key): string
    {
        return "{$this->cachePrefix}:attempts:{$key}";
    }
    
    private function getTimeKey(string $key): string
    {
        return "{$this->cachePrefix}:time:{$key}";
    }
    
    private function getDecayMinutes(string $key): int
    {
        // Default decay minutes, could be made configurable per key
        return 15;
    }
    
    private function logRateLimitEvent(string $message, string $key, int $maxAttempts = 0, int $currentAttempts = 0): void
    {
        if (config('supabase-auth.monitoring.logging.level', 'info') !== 'debug') {
            return;
        }
        
        Log::channel(config('supabase-auth.monitoring.logging.channel', 'stack'))
            ->info('[RateLimiter] ' . $message, [
                'key' => $key,
                'current_attempts' => $currentAttempts ?: $this->attempts($key),
                'max_attempts' => $maxAttempts,
                'available_in' => $maxAttempts ? $this->availableIn($key) : 0,
            ]);
    }
}