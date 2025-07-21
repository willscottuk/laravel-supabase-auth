<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Exceptions;

use RuntimeException;

class ConfigurationException extends RuntimeException
{
    public static function missingConfig(string $key): self
    {
        return new self("Missing required configuration key: {$key}");
    }
    
    public static function invalidConfig(string $key, string $message): self
    {
        return new self("Invalid configuration for {$key}: {$message}");
    }
    
    public static function environmentMismatch(string $expected, string $actual): self
    {
        return new self("Environment mismatch. Expected: {$expected}, Got: {$actual}");
    }
}