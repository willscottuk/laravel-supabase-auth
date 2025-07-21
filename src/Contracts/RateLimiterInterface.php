<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Contracts;

interface RateLimiterInterface
{
    public function attempt(string $key, int $maxAttempts, int $decayMinutes = 1): bool;
    
    public function tooManyAttempts(string $key, int $maxAttempts): bool;
    
    public function availableIn(string $key): int;
    
    public function clear(string $key): void;
    
    public function retriesLeft(string $key, int $maxAttempts): int;
    
    public function hit(string $key, int $decayMinutes = 1): int;
    
    public function attempts(string $key): int;
    
    public function resetAttempts(string $key): bool;
}