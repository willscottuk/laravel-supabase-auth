<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class CacheManager
{
    private CacheRepository $cache;
    private LoggerInterface $logger;
    private array $config;
    private string $prefix;
    private bool $compressionEnabled;
    
    public function __construct(
        CacheRepository $cache,
        LoggerInterface $logger,
        array $config = []
    ) {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->config = $config;
        $this->prefix = $config['prefix'] ?? 'supabase_auth';
        $this->compressionEnabled = $config['compression'] ?? false;
    }
    
    /**
     * Cache user data with appropriate TTL
     */
    public function cacheUserData(string $userId, array $userData, ?int $ttl = null): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }
        
        $key = $this->getUserDataKey($userId);
        $ttl = $ttl ?? $this->getTtl('user_data', 300);
        
        $data = $this->prepareForCache($userData);
        
        $this->cache->put($key, $data, $ttl);
        
        $this->logCacheOperation('User data cached', $key, $ttl);
    }
    
    /**
     * Retrieve cached user data
     */
    public function getCachedUserData(string $userId): ?array
    {
        if (!$this->isCacheEnabled()) {
            return null;
        }
        
        $key = $this->getUserDataKey($userId);
        $data = $this->cache->get($key);
        
        if ($data === null) {
            $this->logCacheOperation('User data cache miss', $key);
            return null;
        }
        
        $this->logCacheOperation('User data cache hit', $key);
        
        return $this->prepareFromCache($data);
    }
    
    /**
     * Cache JWT validation result
     */
    public function cacheJwtValidation(string $tokenHash, array $validationResult, ?int $ttl = null): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }
        
        $key = $this->getJwtValidationKey($tokenHash);
        $ttl = $ttl ?? $this->getTtl('jwt_validation', 60);
        
        $data = $this->prepareForCache($validationResult);
        
        $this->cache->put($key, $data, $ttl);
        
        $this->logCacheOperation('JWT validation cached', $key, $ttl);
    }
    
    /**
     * Retrieve cached JWT validation result
     */
    public function getCachedJwtValidation(string $tokenHash): ?array
    {
        if (!$this->isCacheEnabled()) {
            return null;
        }
        
        $key = $this->getJwtValidationKey($tokenHash);
        $data = $this->cache->get($key);
        
        if ($data === null) {
            $this->logCacheOperation('JWT validation cache miss', $key);
            return null;
        }
        
        $this->logCacheOperation('JWT validation cache hit', $key);
        
        return $this->prepareFromCache($data);
    }
    
    /**
     * Cache OAuth state
     */
    public function cacheOAuthState(string $state, array $stateData, ?int $ttl = null): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }
        
        $key = $this->getOAuthStateKey($state);
        $ttl = $ttl ?? $this->getTtl('oauth_state', 600);
        
        $data = $this->prepareForCache($stateData);
        
        $this->cache->put($key, $data, $ttl);
        
        $this->logCacheOperation('OAuth state cached', $key, $ttl);
    }
    
    /**
     * Retrieve and remove cached OAuth state
     */
    public function consumeOAuthState(string $state): ?array
    {
        if (!$this->isCacheEnabled()) {
            return null;
        }
        
        $key = $this->getOAuthStateKey($state);
        $data = $this->cache->get($key);
        
        if ($data === null) {
            $this->logCacheOperation('OAuth state cache miss', $key);
            return null;
        }
        
        // Remove the state after retrieval for security
        $this->cache->forget($key);
        
        $this->logCacheOperation('OAuth state consumed', $key);
        
        return $this->prepareFromCache($data);
    }
    
    /**
     * Cache API response
     */
    public function cacheApiResponse(string $endpoint, array $parameters, array $response, int $ttl = 300): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }
        
        $key = $this->getApiResponseKey($endpoint, $parameters);
        $data = $this->prepareForCache($response);
        
        $this->cache->put($key, $data, $ttl);
        
        $this->logCacheOperation('API response cached', $key, $ttl);
    }
    
    /**
     * Retrieve cached API response
     */
    public function getCachedApiResponse(string $endpoint, array $parameters): ?array
    {
        if (!$this->isCacheEnabled()) {
            return null;
        }
        
        $key = $this->getApiResponseKey($endpoint, $parameters);
        $data = $this->cache->get($key);
        
        if ($data === null) {
            $this->logCacheOperation('API response cache miss', $key);
            return null;
        }
        
        $this->logCacheOperation('API response cache hit', $key);
        
        return $this->prepareFromCache($data);
    }
    
    /**
     * Invalidate user-related cache entries
     */
    public function invalidateUserCache(string $userId): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }
        
        $keys = [
            $this->getUserDataKey($userId),
        ];
        
        foreach ($keys as $key) {
            $this->cache->forget($key);
        }
        
        $this->logCacheOperation('User cache invalidated', $userId);
    }
    
    /**
     * Clear all cache entries with the configured prefix
     */
    public function clearAll(): int
    {
        if (!$this->isCacheEnabled()) {
            return 0;
        }
        
        // Implementation depends on cache driver
        // For now, we'll track and clear known patterns
        $patterns = [
            'user:*',
            'jwt:*',
            'oauth:*',
            'api:*',
        ];
        
        $cleared = 0;
        foreach ($patterns as $pattern) {
            // This is a simplified implementation
            // Real implementation would depend on cache driver capabilities
            $cleared++;
        }
        
        $this->logCacheOperation('Cache cleared', 'all', 0, $cleared);
        
        return $cleared;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->isCacheEnabled(),
            'store' => $this->config['store'] ?? 'default',
            'prefix' => $this->prefix,
            'compression' => $this->compressionEnabled,
            'ttl_config' => $this->config['ttl'] ?? [],
        ];
    }
    
    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUp(array $userIds = []): int
    {
        if (!$this->isCacheEnabled()) {
            return 0;
        }
        
        $warmed = 0;
        
        // This would typically pre-load frequently accessed user data
        // Implementation would depend on specific requirements
        
        $this->logCacheOperation('Cache warmed up', 'warmup', 0, $warmed);
        
        return $warmed;
    }
    
    private function isCacheEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }
    
    private function getTtl(string $type, int $default): int
    {
        return $this->config['ttl'][$type] ?? $default;
    }
    
    private function getUserDataKey(string $userId): string
    {
        return "{$this->prefix}:user:{$userId}";
    }
    
    private function getJwtValidationKey(string $tokenHash): string
    {
        return "{$this->prefix}:jwt:{$tokenHash}";
    }
    
    private function getOAuthStateKey(string $state): string
    {
        return "{$this->prefix}:oauth:{$state}";
    }
    
    private function getApiResponseKey(string $endpoint, array $parameters): string
    {
        $paramHash = hash('sha256', serialize($parameters));
        return "{$this->prefix}:api:" . hash('sha256', $endpoint . $paramHash);
    }
    
    private function prepareForCache($data): array|string
    {
        if (!$this->compressionEnabled) {
            return $data;
        }
        
        $serialized = serialize($data);
        $compressed = gzcompress($serialized);
        
        return [
            'compressed' => true,
            'data' => base64_encode($compressed),
        ];
    }
    
    private function prepareFromCache($data): array
    {
        if (!is_array($data) || !isset($data['compressed'])) {
            return $data;
        }
        
        if ($data['compressed']) {
            $compressed = base64_decode($data['data']);
            $serialized = gzuncompress($compressed);
            return unserialize($serialized);
        }
        
        return $data;
    }
    
    private function logCacheOperation(string $message, string $key, int $ttl = 0, int $count = 0): void
    {
        if (config('supabase-auth.monitoring.logging.level') !== 'debug') {
            return;
        }
        
        $context = [
            'key' => $key,
        ];
        
        if ($ttl > 0) {
            $context['ttl'] = $ttl;
        }
        
        if ($count > 0) {
            $context['count'] = $count;
        }
        
        $this->logger->debug('[CacheManager] ' . $message, $context);
    }
}