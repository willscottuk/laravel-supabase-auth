<?php

namespace YourVendor\LaravelSupabaseAuth\Tests\Feature;

use Orchestra\Testbench\TestCase;
use YourVendor\LaravelSupabaseAuth\SupabaseAuthServiceProvider;
use YourVendor\LaravelSupabaseAuth\Services\SupabaseAuth;
use YourVendor\LaravelSupabaseAuth\Services\SupabaseClient;

class AuthTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            SupabaseAuthServiceProvider::class,
        ];
    }
    
    protected function defineEnvironment($app)
    {
        $app['config']->set('supabase-auth.url', 'https://test.supabase.co');
        $app['config']->set('supabase-auth.anon_key', 'test-anon-key');
        $app['config']->set('supabase-auth.service_key', 'test-service-key');
        $app['config']->set('supabase-auth.jwt.secret', 'test-jwt-secret');
    }
    
    public function test_supabase_client_can_be_resolved()
    {
        $client = $this->app->make(SupabaseClient::class);
        
        $this->assertInstanceOf(SupabaseClient::class, $client);
        $this->assertEquals('https://test.supabase.co', $client->getUrl());
        $this->assertEquals('test-anon-key', $client->getAnonKey());
    }
    
    public function test_supabase_auth_can_be_resolved()
    {
        $auth = $this->app->make(SupabaseAuth::class);
        
        $this->assertInstanceOf(SupabaseAuth::class, $auth);
    }
    
    public function test_auth_routes_are_registered()
    {
        $routes = $this->app['router']->getRoutes();
        
        $this->assertTrue($routes->hasNamedRoute('supabase.auth.login'));
        $this->assertTrue($routes->hasNamedRoute('supabase.auth.register'));
        $this->assertTrue($routes->hasNamedRoute('supabase.auth.logout'));
        $this->assertTrue($routes->hasNamedRoute('supabase.auth.user'));
    }
    
    public function test_login_validation_fails_with_invalid_data()
    {
        $response = $this->postJson('/auth/supabase/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'messages' => [
                    'email',
                    'password',
                ],
            ]);
    }
    
    public function test_register_validation_fails_with_invalid_data()
    {
        $response = $this->postJson('/auth/supabase/register', [
            'email' => 'invalid-email',
            'password' => '123',
        ]);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'messages' => [
                    'email',
                    'password',
                ],
            ]);
    }
    
    public function test_password_reset_validation_fails_with_invalid_email()
    {
        $response = $this->postJson('/auth/supabase/password/reset', [
            'email' => 'invalid-email',
        ]);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'messages' => [
                    'email',
                ],
            ]);
    }
    
    public function test_otp_verification_validation_fails_with_missing_data()
    {
        $response = $this->postJson('/auth/supabase/otp/verify', [
            'email' => 'test@example.com',
        ]);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'messages' => [
                    'token',
                ],
            ]);
    }
    
    public function test_user_endpoint_returns_401_when_not_authenticated()
    {
        $response = $this->getJson('/auth/supabase/user');
        
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthenticated',
            ]);
    }
    
    public function test_refresh_endpoint_fails_without_valid_refresh_token()
    {
        $response = $this->postJson('/auth/supabase/refresh');
        
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Token refresh failed',
            ]);
    }
}