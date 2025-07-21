<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Exceptions;

use Exception;

class CircuitBreakerException extends Exception
{
    public static function circuitOpen(string $service): self
    {
        return new self("Circuit breaker is open for service: {$service}", 503);
    }
    
    public static function serviceUnavailable(string $service): self
    {
        return new self("Service unavailable: {$service}", 503);
    }
}