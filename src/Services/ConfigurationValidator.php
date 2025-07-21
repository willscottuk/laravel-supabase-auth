<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Supabase\LaravelAuth\Exceptions\ConfigurationException;

class ConfigurationValidator
{
    private array $requiredConfig = [
        'supabase-auth.url',
        'supabase-auth.anon_key',
        'supabase-auth.service_key',
        'supabase-auth.jwt.secret',
    ];
    
    private array $validationRules = [
        'supabase-auth.url' => 'required|url',
        'supabase-auth.anon_key' => 'required|string|min:1',
        'supabase-auth.service_key' => 'required|string|min:1',
        'supabase-auth.jwt.secret' => 'required|string|min:32',
        'supabase-auth.jwt.algorithm' => 'string|in:HS256,HS384,HS512,RS256,RS384,RS512',
        'supabase-auth.jwt.ttl' => 'integer|min:300|max:86400',
        'supabase-auth.client.timeout' => 'numeric|min:1|max:300',
        'supabase-auth.client.connect_timeout' => 'numeric|min:1|max:60',
        'supabase-auth.client.retry_attempts' => 'integer|min:0|max:10',
    ];
    
    public function validate(): void
    {
        $this->validateRequiredConfig();
        $this->validateConfigFormat();
        $this->validateEnvironmentSpecificConfig();
        $this->validateSecurityConfig();
    }
    
    private function validateRequiredConfig(): void
    {
        foreach ($this->requiredConfig as $key) {
            $value = config($key);
            
            if (empty($value)) {
                throw ConfigurationException::missingConfig($key);
            }
        }
    }
    
    private function validateConfigFormat(): void
    {
        $configData = [];
        
        foreach ($this->validationRules as $key => $rules) {
            $configData[$key] = config($key);
        }
        
        $validator = Validator::make($configData, $this->validationRules);
        
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            throw ConfigurationException::invalidConfig(
                'validation',
                implode(', ', $errors)
            );
        }
    }
    
    private function validateEnvironmentSpecificConfig(): void
    {
        $environment = config('app.env', 'production');
        $envConfig = config("supabase-auth.environment.{$environment}");
        
        if ($envConfig === null) {
            throw ConfigurationException::environmentMismatch(
                'production|staging|development',
                $environment
            );
        }
        
        // Validate production-specific requirements
        if ($environment === 'production') {
            $this->validateProductionConfig();
        }
    }
    
    private function validateProductionConfig(): void
    {
        $url = config('supabase-auth.url');
        
        if (!str_starts_with($url, 'https://')) {
            throw ConfigurationException::invalidConfig(
                'supabase-auth.url',
                'HTTPS is required in production environment'
            );
        }
        
        if (!config('supabase-auth.security.secure_cookies')) {
            throw ConfigurationException::invalidConfig(
                'supabase-auth.security.secure_cookies',
                'Secure cookies are required in production environment'
            );
        }
        
        if (!config('supabase-auth.client.verify_ssl')) {
            throw ConfigurationException::invalidConfig(
                'supabase-auth.client.verify_ssl',
                'SSL verification is required in production environment'
            );
        }
    }
    
    private function validateSecurityConfig(): void
    {
        $jwtSecret = config('supabase-auth.jwt.secret');
        
        if (strlen($jwtSecret) < 32) {
            throw ConfigurationException::invalidConfig(
                'supabase-auth.jwt.secret',
                'JWT secret must be at least 32 characters long'
            );
        }
        
        // Validate password policy
        $passwordPolicy = config('supabase-auth.security.password_policy');
        
        if ($passwordPolicy['min_length'] < 6) {
            throw ConfigurationException::invalidConfig(
                'supabase-auth.security.password_policy.min_length',
                'Minimum password length must be at least 6 characters'
            );
        }
    }
    
    public function validateConnection(): array
    {
        try {
            $client = app(\Supabase\LaravelAuth\Services\SupabaseClient::class);
            
            // Test connection with a simple health check
            $response = $client->request('GET', '/rest/v1/', [], true);
            
            return [
                'status' => 'success',
                'message' => 'Successfully connected to Supabase',
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to connect to Supabase: ' . $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }
    
    public function getConfigSummary(): array
    {
        return [
            'url' => config('supabase-auth.url'),
            'environment' => config('app.env'),
            'cache_enabled' => config('supabase-auth.cache.enabled'),
            'rate_limiting_enabled' => config('supabase-auth.rate_limiting.enabled'),
            'circuit_breaker_enabled' => config('supabase-auth.circuit_breaker.enabled'),
            'monitoring_enabled' => config('supabase-auth.monitoring.enabled'),
            'security' => [
                'secure_cookies' => config('supabase-auth.security.secure_cookies'),
                'csrf_protection' => config('supabase-auth.security.csrf_protection'),
                'password_min_length' => config('supabase-auth.security.password_policy.min_length'),
            ],
        ];
    }
}