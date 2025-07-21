<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Supabase\LaravelAuth\Contracts\CircuitBreakerInterface;
use Supabase\LaravelAuth\Exceptions\CircuitBreakerException;

class CircuitBreaker implements CircuitBreakerInterface
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';
    
    private int $failureThreshold;
    private int $recoveryTimeout;
    private array $expectedExceptionTypes;
    private string $cachePrefix;
    
    public function __construct()
    {
        $this->failureThreshold = config('supabase-auth.circuit_breaker.failure_threshold', 5);
        $this->recoveryTimeout = config('supabase-auth.circuit_breaker.recovery_timeout', 60);
        $this->expectedExceptionTypes = config('supabase-auth.circuit_breaker.expected_exception_types', []);
        $this->cachePrefix = config('supabase-auth.cache.prefix', 'supabase_auth') . ':cb';
    }
    
    public function call(Closure $callback, string $service = 'default')
    {
        if (!config('supabase-auth.circuit_breaker.enabled', true)) {
            return $callback();
        }
        
        if ($this->isOpen($service)) {
            $this->logCircuitBreakerEvent('Circuit breaker is open', $service);
            throw CircuitBreakerException::circuitOpen($service);
        }
        
        try {
            $result = $callback();
            $this->recordSuccess($service);
            return $result;
        } catch (\Exception $exception) {
            if ($this->shouldRecordFailure($exception)) {
                $this->recordFailure($service);
                
                if ($this->getFailureCount($service) >= $this->failureThreshold) {
                    $this->openCircuit($service);
                    $this->logCircuitBreakerEvent('Circuit breaker opened due to failures', $service);
                }
            }
            
            throw $exception;
        }
    }
    
    public function isOpen(string $service = 'default'): bool
    {
        $state = $this->getState($service);
        
        if ($state === self::STATE_OPEN) {
            // Check if recovery timeout has passed
            $openedAt = Cache::get($this->getOpenedAtKey($service));
            
            if ($openedAt && (time() - $openedAt) >= $this->recoveryTimeout) {
                $this->setHalfOpen($service);
                $this->logCircuitBreakerEvent('Circuit breaker moved to half-open state', $service);
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    public function isHalfOpen(string $service = 'default'): bool
    {
        return $this->getState($service) === self::STATE_HALF_OPEN;
    }
    
    public function isClosed(string $service = 'default'): bool
    {
        return $this->getState($service) === self::STATE_CLOSED;
    }
    
    public function recordSuccess(string $service = 'default'): void
    {
        if ($this->isHalfOpen($service)) {
            $this->closeCircuit($service);
            $this->logCircuitBreakerEvent('Circuit breaker closed after successful call', $service);
        }
        
        // Reset failure count on success
        Cache::forget($this->getFailureCountKey($service));
    }
    
    public function recordFailure(string $service = 'default'): void
    {
        $failureCount = $this->getFailureCount($service) + 1;
        
        Cache::put(
            $this->getFailureCountKey($service),
            $failureCount,
            now()->addSeconds($this->recoveryTimeout * 2)
        );
        
        $this->logCircuitBreakerEvent("Recorded failure #{$failureCount}", $service);
    }
    
    public function getFailureCount(string $service = 'default'): int
    {
        return Cache::get($this->getFailureCountKey($service), 0);
    }
    
    public function reset(string $service = 'default'): void
    {
        Cache::forget($this->getStateKey($service));
        Cache::forget($this->getFailureCountKey($service));
        Cache::forget($this->getOpenedAtKey($service));
        
        $this->logCircuitBreakerEvent('Circuit breaker reset', $service);
    }
    
    private function getState(string $service): string
    {
        return Cache::get($this->getStateKey($service), self::STATE_CLOSED);
    }
    
    private function setState(string $service, string $state): void
    {
        Cache::put(
            $this->getStateKey($service),
            $state,
            now()->addSeconds($this->recoveryTimeout * 2)
        );
    }
    
    private function openCircuit(string $service): void
    {
        $this->setState($service, self::STATE_OPEN);
        Cache::put(
            $this->getOpenedAtKey($service),
            time(),
            now()->addSeconds($this->recoveryTimeout * 2)
        );
    }
    
    private function setHalfOpen(string $service): void
    {
        $this->setState($service, self::STATE_HALF_OPEN);
        Cache::forget($this->getOpenedAtKey($service));
    }
    
    private function closeCircuit(string $service): void
    {
        $this->setState($service, self::STATE_CLOSED);
        Cache::forget($this->getFailureCountKey($service));
        Cache::forget($this->getOpenedAtKey($service));
    }
    
    private function shouldRecordFailure(\Exception $exception): bool
    {
        if (empty($this->expectedExceptionTypes)) {
            return true;
        }
        
        foreach ($this->expectedExceptionTypes as $exceptionType) {
            if ($exception instanceof $exceptionType) {
                return true;
            }
        }
        
        return false;
    }
    
    private function getStateKey(string $service): string
    {
        return "{$this->cachePrefix}:{$service}:state";
    }
    
    private function getFailureCountKey(string $service): string
    {
        return "{$this->cachePrefix}:{$service}:failures";
    }
    
    private function getOpenedAtKey(string $service): string
    {
        return "{$this->cachePrefix}:{$service}:opened_at";
    }
    
    private function logCircuitBreakerEvent(string $message, string $service): void
    {
        if (config('supabase-auth.monitoring.logging.level', 'info') !== 'debug') {
            return;
        }
        
        Log::channel(config('supabase-auth.monitoring.logging.channel', 'stack'))
            ->info('[CircuitBreaker] ' . $message, [
                'service' => $service,
                'failure_count' => $this->getFailureCount($service),
                'state' => $this->getState($service),
            ]);
    }
}