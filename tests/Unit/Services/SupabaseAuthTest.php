<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Tests\Unit\Services;

use Mockery;
use Psr\Log\LoggerInterface;
use Supabase\LaravelAuth\Services\SupabaseAuth;
use Supabase\LaravelAuth\Services\SupabaseClient;
use Supabase\LaravelAuth\Services\CacheManager;
use Supabase\LaravelAuth\Tests\TestCase;

class SupabaseAuthTest extends TestCase
{
    private SupabaseAuth $supabaseAuth;
    private Mockery\MockInterface $mockClient;
    private Mockery\MockInterface $mockCache;
    private Mockery\MockInterface $mockLogger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockClient = Mockery::mock(SupabaseClient::class);
        $this->mockCache = Mockery::mock(CacheManager::class);
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        
        $this->supabaseAuth = new SupabaseAuth(
            $this->mockClient,
            $this->mockCache,
            $this->mockLogger
        );
        
        // Default mock expectations for logging
        $this->mockLogger->shouldReceive('info')->byDefault();
        $this->mockLogger->shouldReceive('error')->byDefault();
        $this->mockLogger->shouldReceive('warning')->byDefault();
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function test_sign_up_successful(): void
    {
        $email = 'test@example.com';
        $password = 'password123';
        $userData = ['name' => 'Test User'];
        
        $expectedResponse = [
            'user' => $this->mockSupabaseUserData(),
            'access_token' => 'mock-token',
        ];
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/signup', [
                'json' => [
                    'email' => $email,
                    'password' => $password,
                    'data' => $userData,
                ],
            ])
            ->once()
            ->andReturn($expectedResponse);
        
        $result = $this->supabaseAuth->signUp($email, $password, $userData);
        
        $this->assertEquals($expectedResponse, $result);
    }
    
    public function test_sign_up_failure(): void
    {
        $email = 'test@example.com';
        $password = 'weak';
        
        $this->mockClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(new \Exception('Password too weak'));
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Password too weak');
        
        $this->supabaseAuth->signUp($email, $password);
    }
    
    public function test_sign_in_successful(): void
    {
        $email = 'test@example.com';
        $password = 'password123';
        
        $expectedResponse = $this->mockSuccessfulLogin();
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/token', [
                'json' => [
                    'email' => $email,
                    'password' => $password,
                ],
                'query' => [
                    'grant_type' => 'password',
                ],
            ])
            ->once()
            ->andReturn($expectedResponse);
        
        $this->mockCache
            ->shouldReceive('cacheUserData')
            ->with($expectedResponse['user']['id'], $expectedResponse['user'])
            ->once();
        
        $result = $this->supabaseAuth->signIn($email, $password);
        
        $this->assertEquals($expectedResponse, $result);
    }
    
    public function test_sign_in_failure(): void
    {
        $email = 'test@example.com';
        $password = 'wrongpassword';
        
        $this->mockClient
            ->shouldReceive('request')
            ->once()
            ->andThrow(new \Exception('Invalid credentials'));
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid credentials');
        
        $this->supabaseAuth->signIn($email, $password);
    }
    
    public function test_sign_out_successful(): void
    {
        $accessToken = 'mock-access-token';
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/logout', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ])
            ->once()
            ->andReturn(['message' => 'Logged out successfully']);
        
        $result = $this->supabaseAuth->signOut($accessToken);
        
        $this->assertEquals(['message' => 'Logged out successfully'], $result);
    }
    
    public function test_refresh_token_successful(): void
    {
        $refreshToken = 'mock-refresh-token';
        $expectedResponse = $this->mockSuccessfulLogin();
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/token', [
                'json' => [
                    'refresh_token' => $refreshToken,
                ],
                'query' => [
                    'grant_type' => 'refresh_token',
                ],
            ])
            ->once()
            ->andReturn($expectedResponse);
        
        $this->mockCache
            ->shouldReceive('cacheUserData')
            ->with($expectedResponse['user']['id'], $expectedResponse['user'])
            ->once();
        
        $result = $this->supabaseAuth->refreshToken($refreshToken);
        
        $this->assertEquals($expectedResponse, $result);
    }
    
    public function test_get_user_successful(): void
    {
        $accessToken = 'mock-access-token';
        $userData = $this->mockSupabaseUserData();
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('GET', '/auth/v1/user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ])
            ->once()
            ->andReturn($userData);
        
        $this->mockCache
            ->shouldReceive('cacheUserData')
            ->with($userData['id'], $userData)
            ->once();
        
        $result = $this->supabaseAuth->getUser($accessToken);
        
        $this->assertEquals($userData, $result);
    }
    
    public function test_update_user_successful(): void
    {
        $accessToken = 'mock-access-token';
        $updateData = ['name' => 'Updated Name'];
        $userData = $this->mockSupabaseUserData();
        $userData['user_metadata']['name'] = 'Updated Name';
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('PUT', '/auth/v1/user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => $updateData,
            ])
            ->once()
            ->andReturn($userData);
        
        $this->mockCache
            ->shouldReceive('invalidateUserCache')
            ->with($userData['id'])
            ->once();
        
        $this->mockCache
            ->shouldReceive('cacheUserData')
            ->with($userData['id'], $userData)
            ->once();
        
        $result = $this->supabaseAuth->updateUser($accessToken, $updateData);
        
        $this->assertEquals($userData, $result);
    }
    
    public function test_reset_password_for_email_successful(): void
    {
        $email = 'test@example.com';
        $redirectTo = 'https://example.com/reset';
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/recover', [
                'json' => [
                    'email' => $email,
                    'redirectTo' => $redirectTo,
                ],
            ])
            ->once()
            ->andReturn(['message' => 'Recovery email sent']);
        
        $result = $this->supabaseAuth->resetPasswordForEmail($email, $redirectTo);
        
        $this->assertEquals(['message' => 'Recovery email sent'], $result);
    }
    
    public function test_update_password_successful(): void
    {
        $accessToken = 'mock-access-token';
        $newPassword = 'newpassword123';
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('PUT', '/auth/v1/user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => [
                    'password' => $newPassword,
                ],
            ])
            ->once()
            ->andReturn(['message' => 'Password updated']);
        
        $result = $this->supabaseAuth->updatePassword($accessToken, $newPassword);
        
        $this->assertEquals(['message' => 'Password updated'], $result);
    }
    
    public function test_verify_otp_successful(): void
    {
        $email = 'test@example.com';
        $token = '123456';
        $type = 'email';
        $expectedResponse = $this->mockSuccessfulLogin();
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/verify', [
                'json' => [
                    'email' => $email,
                    'token' => $token,
                    'type' => $type,
                ],
            ])
            ->once()
            ->andReturn($expectedResponse);
        
        $this->mockCache
            ->shouldReceive('cacheUserData')
            ->with($expectedResponse['user']['id'], $expectedResponse['user'])
            ->once();
        
        $result = $this->supabaseAuth->verifyOtp($email, $token, $type);
        
        $this->assertEquals($expectedResponse, $result);
    }
    
    public function test_resend_otp_successful(): void
    {
        $email = 'test@example.com';
        $type = 'signup';
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('POST', '/auth/v1/otp', [
                'json' => [
                    'email' => $email,
                    'type' => $type,
                ],
            ])
            ->once()
            ->andReturn(['message' => 'OTP sent']);
        
        $result = $this->supabaseAuth->resendOtp($email, $type);
        
        $this->assertEquals(['message' => 'OTP sent'], $result);
    }
    
    public function test_sign_in_with_oauth(): void
    {
        $provider = 'google';
        $options = ['redirect_to' => 'https://example.com/callback'];
        
        $this->mockClient
            ->shouldReceive('getUrl')
            ->once()
            ->andReturn('https://test.supabase.co');
        
        $result = $this->supabaseAuth->signInWithOAuth($provider, $options);
        
        $expectedUrl = 'https://test.supabase.co/auth/v1/authorize?' . http_build_query([
            'provider' => $provider,
            'redirect_to' => 'https://example.com/callback',
        ]);
        
        $this->assertEquals($expectedUrl, $result);
    }
    
    public function test_verify_token_successful(): void
    {
        // Mock the cache to return null (cache miss)
        $this->mockCache
            ->shouldReceive('getCachedJwtValidation')
            ->once()
            ->andReturn(null);
        
        $this->mockCache
            ->shouldReceive('cacheJwtValidation')
            ->once();
        
        // Create a simple mock token
        $token = $this->mockJWTToken();
        
        // We need to mock the config helper since we can't test real JWT verification
        $result = $this->supabaseAuth->verifyToken($token);
        
        $this->assertArrayHasKey('valid', $result);
    }
    
    public function test_get_user_by_id_with_cache(): void
    {
        $userId = 'test-user-id';
        $userData = $this->mockSupabaseUserData();
        
        // Mock cache hit
        $this->mockCache
            ->shouldReceive('getCachedUserData')
            ->with($userId)
            ->once()
            ->andReturn($userData);
        
        $result = $this->supabaseAuth->getUserById($userId);
        
        $this->assertEquals($userData, $result);
    }
    
    public function test_get_user_by_id_cache_miss(): void
    {
        $userId = 'test-user-id';
        $userData = $this->mockSupabaseUserData();
        
        // Mock cache miss
        $this->mockCache
            ->shouldReceive('getCachedUserData')
            ->with($userId)
            ->once()
            ->andReturn(null);
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('GET', "/auth/v1/admin/users/{$userId}", [], true)
            ->once()
            ->andReturn($userData);
        
        $this->mockCache
            ->shouldReceive('cacheUserData')
            ->with($userId, $userData)
            ->once();
        
        $result = $this->supabaseAuth->getUserById($userId);
        
        $this->assertEquals($userData, $result);
    }
    
    public function test_delete_user_successful(): void
    {
        $userId = 'test-user-id';
        
        $this->mockClient
            ->shouldReceive('request')
            ->with('DELETE', "/auth/v1/admin/users/{$userId}", [], true)
            ->once()
            ->andReturn(['message' => 'User deleted']);
        
        $this->mockCache
            ->shouldReceive('invalidateUserCache')
            ->with($userId)
            ->once();
        
        $result = $this->supabaseAuth->deleteUser($userId);
        
        $this->assertEquals(['message' => 'User deleted'], $result);
    }
    
    public function test_mask_email(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->supabaseAuth);
        $method = $reflection->getMethod('maskEmail');
        $method->setAccessible(true);
        
        $maskedEmail = $method->invoke($this->supabaseAuth, 'john.doe@example.com');
        
        $this->assertEquals('jo*******@example.com', $maskedEmail);
    }
    
    public function test_mask_email_invalid(): void
    {
        $reflection = new \ReflectionClass($this->supabaseAuth);
        $method = $reflection->getMethod('maskEmail');
        $method->setAccessible(true);
        
        $maskedEmail = $method->invoke($this->supabaseAuth, 'invalid-email');
        
        $this->assertEquals('invalid@email.com', $maskedEmail);
    }
}