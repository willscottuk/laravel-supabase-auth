<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Supabase\LaravelAuth\SupabaseAuthServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setUpDatabase();
    }
    
    protected function getPackageProviders($app): array
    {
        return [
            SupabaseAuthServiceProvider::class,
        ];
    }
    
    protected function defineEnvironment($app): void
    {
        // Basic configuration for testing
        $app['config']->set('supabase-auth.url', 'https://test.supabase.co');
        $app['config']->set('supabase-auth.anon_key', 'test-anon-key');
        $app['config']->set('supabase-auth.service_key', 'test-service-key');
        $app['config']->set('supabase-auth.jwt.secret', str_repeat('a', 32)); // 32 character secret
        
        // Disable features that require external services in tests
        $app['config']->set('supabase-auth.cache.enabled', false);
        $app['config']->set('supabase-auth.circuit_breaker.enabled', false);
        $app['config']->set('supabase-auth.rate_limiting.enabled', false);
        
        // Set up testing database
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Set auth defaults
        $app['config']->set('auth.defaults.guard', 'supabase');
        $app['config']->set('auth.defaults.provider', 'supabase');
    }
    
    protected function setUpDatabase(): void
    {
        $migration = include __DIR__ . '/../database/migrations/2024_01_01_000000_create_supabase_users_table.php';
        $migration->up();
    }
    
    protected function mockSupabaseResponse(array $response): \Mockery\MockInterface
    {
        return \Mockery::mock(\Supabase\LaravelAuth\Services\SupabaseClient::class)
            ->shouldReceive('request')
            ->andReturn($response)
            ->getMock();
    }
    
    protected function createTestUser(array $attributes = []): \Supabase\LaravelAuth\Models\SupabaseUser
    {
        $user = new \Supabase\LaravelAuth\Models\SupabaseUser();
        
        $defaultAttributes = [
            'id' => 'test-user-id-' . uniqid(),
            'email' => 'test@example.com',
            'name' => 'Test User',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        $user->fill(array_merge($defaultAttributes, $attributes));
        $user->save();
        
        return $user;
    }
    
    protected function mockSupabaseUserData(): array
    {
        return [
            'id' => 'test-user-id-' . uniqid(),
            'email' => 'test@example.com',
            'email_confirmed_at' => now()->toISOString(),
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'user_metadata' => [
                'name' => 'Test User',
                'avatar_url' => 'https://example.com/avatar.jpg',
            ],
            'app_metadata' => [
                'provider' => 'email',
                'role' => 'user',
            ],
        ];
    }
    
    protected function mockSuccessfulLogin(): array
    {
        return [
            'access_token' => 'mock-access-token',
            'refresh_token' => 'mock-refresh-token',
            'user' => $this->mockSupabaseUserData(),
            'expires_in' => 3600,
        ];
    }
    
    protected function mockJWTToken(): string
    {
        // Create a simple mock JWT for testing
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'sub' => 'test-user-id',
            'email' => 'test@example.com',
            'exp' => time() + 3600,
            'iat' => time(),
        ]));
        
        return $header . '.' . $payload . '.mock-signature';
    }
}