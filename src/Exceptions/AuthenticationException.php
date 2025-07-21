<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Exceptions;

use Exception;

class AuthenticationException extends Exception
{
    public static function invalidCredentials(): self
    {
        return new self('Invalid authentication credentials provided', 401);
    }
    
    public static function tokenExpired(): self
    {
        return new self('Authentication token has expired', 401);
    }
    
    public static function tokenInvalid(): self
    {
        return new self('Authentication token is invalid', 401);
    }
    
    public static function userNotFound(): self
    {
        return new self('User not found', 404);
    }
    
    public static function emailNotVerified(): self
    {
        return new self('Email address not verified', 403);
    }
    
    public static function accountDisabled(): self
    {
        return new self('User account has been disabled', 403);
    }
    
    public static function rateLimitExceeded(int $retryAfter = null): self
    {
        $message = 'Rate limit exceeded';
        if ($retryAfter) {
            $message .= ". Retry after {$retryAfter} seconds";
        }
        
        return new self($message, 429);
    }
}