<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Contracts;

use Closure;

interface CircuitBreakerInterface
{
    public function call(Closure $callback, string $service = 'default');
    
    public function isOpen(string $service = 'default'): bool;
    
    public function isHalfOpen(string $service = 'default'): bool;
    
    public function isClosed(string $service = 'default'): bool;
    
    public function recordSuccess(string $service = 'default'): void;
    
    public function recordFailure(string $service = 'default'): void;
    
    public function getFailureCount(string $service = 'default'): int;
    
    public function reset(string $service = 'default'): void;
}