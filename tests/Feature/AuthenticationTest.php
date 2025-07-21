<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Tests\Feature;

use Mockery;
use Illuminate\Support\Facades\Auth;
use Supabase\LaravelAuth\Services\SupabaseClient;
use Supabase\LaravelAuth\Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the SupabaseClient to avoid actual HTTP requests
        $this->mockClient = Mockery::mock(SupabaseClient::class);
        $this->app->instance(SupabaseClient::class, $this->mockClient);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function test_registration_endpoint_validation(): void
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
    
    public function test_registration_endpoint_success(): void
    {
        $userData = $this->mockSupabaseUserData();
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/signup', Mockery::type('array'))
            ->once()
            ->andReturn([
                'user' => $userData,
                'access_token' => 'mock-token',
            ]);
        
        $response = $this->postJson('/auth/supabase/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
            'name' => 'Test User',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'email',
                ],
            ]);
    }
    
    public function test_login_endpoint_validation(): void
    {
        $response = $this->postJson('/auth/supabase/login', [
            'email' => 'invalid-email',
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
    
    public function test_login_endpoint_invalid_credentials(): void
    {
        $this->mockClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(new \Exception('Invalid credentials'));
        
        $response = $this->postJson('/auth/supabase/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);
        
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid credentials',
            ]);
    }
    
    public function test_logout_endpoint(): void
    {
        // Create a test user first
        $user = $this->createTestUser();
        $user->setAccessToken('mock-token');
        
        // Mock the logout API call
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/logout', Mockery::type('array'))
            ->once()
            ->andReturn(['message' => 'Logged out']);
        
        // Authenticate the user
        Auth::login($user);
        
        $response = $this->postJson('/auth/supabase/logout');
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logout successful',
            ]);
        
        $this->assertNull(Auth::user());
    }
    
    public function test_user_endpoint_unauthenticated(): void
    {
        $response = $this->getJson('/auth/supabase/user');
        
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthenticated',
            ]);
    }
    
    public function test_user_endpoint_authenticated(): void
    {
        $user = $this->createTestUser();
        Auth::login($user);
        
        $response = $this->getJson('/auth/supabase/user');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'email',
                    'name',
                ],
            ]);
    }
    
    public function test_password_reset_endpoint(): void
    {
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/recover', Mockery::type('array'))
            ->once()
            ->andReturn(['message' => 'Recovery email sent']);
        
        $response = $this->postJson('/auth/supabase/password/reset', [
            'email' => 'test@example.com',
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password reset email sent successfully.',
            ]);
    }
    
    public function test_password_reset_validation(): void
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
    
    public function test_update_password_endpoint_authenticated(): void
    {
        $user = $this->createTestUser();
        $user->setAccessToken('mock-token');
        Auth::login($user);
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('PUT', '/auth/v1/user', Mockery::type('array'))
            ->once()
            ->andReturn(['message' => 'Password updated']);
        
        $response = $this->postJson('/auth/supabase/password/update', [
            'password' => 'newpassword123',
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password updated successfully',
            ]);
    }
    
    public function test_update_password_unauthenticated(): void
    {
        $response = $this->postJson('/auth/supabase/password/update', [
            'password' => 'newpassword123',
        ]);
        
        $response->assertStatus(401);
    }
    
    public function test_otp_verification_endpoint(): void
    {
        $loginResponse = $this->mockSuccessfulLogin();
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/verify', Mockery::type('array'))
            ->once()
            ->andReturn($loginResponse);
        
        $response = $this->postJson('/auth/supabase/otp/verify', [
            'email' => 'test@example.com',
            'token' => '123456',
            'type' => 'email',
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
                'access_token',
            ]);
    }
    
    public function test_otp_verification_validation(): void
    {
        $response = $this->postJson('/auth/supabase/otp/verify', [
            'email' => 'test@example.com',
            // Missing token
        ]);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'error',
                'messages' => [
                    'token',
                ],
            ]);
    }
    
    public function test_otp_resend_endpoint(): void
    {
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/otp', Mockery::type('array'))
            ->once()
            ->andReturn(['message' => 'OTP sent']);
        
        $response = $this->postJson('/auth/supabase/otp/resend', [
            'email' => 'test@example.com',
            'type' => 'signup',
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'OTP resent successfully',
            ]);
    }
    
    public function test_refresh_token_endpoint(): void
    {
        // This test is more complex as it requires mocking the guard's refresh functionality
        $response = $this->postJson('/auth/supabase/refresh');
        
        $response->assertStatus(401); // Should fail without valid refresh token
    }
    
    public function test_auth_routes_are_registered(): void
    {
        $routes = collect($this->app['router']->getRoutes())
            ->map(function ($route) {
                return $route->getName();
            })
            ->filter()
            ->values()
            ->toArray();
        
        $this->assertContains('supabase.auth.login', $routes);
        $this->assertContains('supabase.auth.register', $routes);
        $this->assertContains('supabase.auth.logout', $routes);
        $this->assertContains('supabase.auth.user', $routes);
        $this->assertContains('supabase.auth.refresh', $routes);
        $this->assertContains('supabase.auth.password.reset', $routes);
        $this->assertContains('supabase.auth.password.update', $routes);
        $this->assertContains('supabase.auth.otp.verify', $routes);
        $this->assertContains('supabase.auth.otp.resend', $routes);
        $this->assertContains('supabase.auth.callback', $routes);
    }
    
    public function test_callback_route_redirects(): void
    {
        $response = $this->get('/auth/supabase/callback');
        
        $response->assertRedirect('/dashboard');
    }
}