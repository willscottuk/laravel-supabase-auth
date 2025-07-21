<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Tests\Unit\Models;

use Supabase\LaravelAuth\Models\SupabaseUser;
use Supabase\LaravelAuth\Tests\TestCase;

class SupabaseUserTest extends TestCase
{
    public function test_user_creation(): void
    {
        $user = new SupabaseUser([
            'id' => 'test-user-id',
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
        
        $this->assertEquals('test-user-id', $user->id);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('Test User', $user->name);
    }
    
    public function test_auth_identifier(): void
    {
        $user = new SupabaseUser(['id' => 'test-user-id']);
        
        $this->assertEquals('id', $user->getAuthIdentifierName());
        $this->assertEquals('test-user-id', $user->getAuthIdentifier());
        $this->assertEquals('', $user->getAuthPassword());
    }
    
    public function test_set_supabase_data(): void
    {
        $user = new SupabaseUser();
        
        $supabaseData = [
            'id' => 'supabase-user-id',
            'email' => 'supabase@example.com',
            'email_confirmed_at' => '2024-01-01T12:00:00Z',
            'user_metadata' => [
                'name' => 'Supabase User',
                'avatar_url' => 'https://example.com/avatar.jpg',
            ],
            'phone' => '+1234567890',
        ];
        
        $user->setSupabaseData($supabaseData);
        
        $this->assertEquals('supabase-user-id', $user->id);
        $this->assertEquals('supabase@example.com', $user->email);
        $this->assertEquals('+1234567890', $user->phone);
        $this->assertEquals('https://example.com/avatar.jpg', $user->avatar_url);
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals($supabaseData, $user->getSupabaseData());
    }
    
    public function test_access_token_management(): void
    {
        $user = new SupabaseUser();
        
        $this->assertNull($user->getAccessToken());
        
        $user->setAccessToken('test-access-token');
        
        $this->assertEquals('test-access-token', $user->getAccessToken());
    }
    
    public function test_email_verification(): void
    {
        $user = new SupabaseUser();
        
        $this->assertFalse($user->hasVerifiedEmail());
        
        $user->markEmailAsVerified();
        
        $this->assertTrue($user->hasVerifiedEmail());
    }
    
    public function test_full_name_attribute(): void
    {
        $user = new SupabaseUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        
        $this->assertEquals('Test User', $user->getFullNameAttribute());
        
        // Test with no name but with metadata
        $user = new SupabaseUser(['email' => 'test@example.com']);
        $user->setSupabaseData([
            'id' => 'test-id',
            'user_metadata' => [
                'full_name' => 'Metadata User',
            ],
        ]);
        
        $this->assertEquals('Metadata User', $user->getFullNameAttribute());
        
        // Test fallback to email
        $user = new SupabaseUser(['email' => 'fallback@example.com']);
        $user->setSupabaseData([
            'id' => 'test-id',
            'user_metadata' => [],
        ]);
        
        $this->assertEquals('fallback@example.com', $user->getFullNameAttribute());
    }
    
    public function test_initials_attribute(): void
    {
        $user = new SupabaseUser(['name' => 'John Doe']);
        
        $this->assertEquals('JD', $user->getInitialsAttribute());
        
        $user = new SupabaseUser(['name' => 'SingleName']);
        
        $this->assertEquals('SI', $user->getInitialsAttribute());
    }
    
    public function test_avatar_attribute(): void
    {
        // Test with avatar_url set
        $user = new SupabaseUser(['avatar_url' => 'https://example.com/custom-avatar.jpg']);
        
        $this->assertEquals('https://example.com/custom-avatar.jpg', $user->getAvatarAttribute());
        
        // Test with Gravatar fallback
        $user = new SupabaseUser(['email' => 'test@example.com']);
        
        $expectedHash = md5(strtolower(trim('test@example.com')));
        $expectedUrl = "https://www.gravatar.com/avatar/{$expectedHash}?d=identicon&s=200";
        
        $this->assertEquals($expectedUrl, $user->getAvatarAttribute());
    }
    
    public function test_role_methods(): void
    {
        $user = new SupabaseUser();
        $user->setSupabaseData([
            'id' => 'test-id',
            'app_metadata' => [
                'role' => 'admin',
            ],
        ]);
        
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('user'));
        $this->assertTrue($user->hasAnyRole(['admin', 'moderator']));
        $this->assertFalse($user->hasAnyRole(['user', 'guest']));
        $this->assertTrue($user->isAdmin());
    }
    
    public function test_subscription_methods(): void
    {
        $user = new SupabaseUser();
        $user->setSupabaseData([
            'id' => 'test-id',
            'app_metadata' => [
                'subscription_active' => true,
                'subscription_plan' => 'premium',
            ],
        ]);
        
        $this->assertTrue($user->isSubscribed());
        $this->assertEquals('premium', $user->getSubscriptionPlan());
    }
    
    public function test_timezone_method(): void
    {
        $user = new SupabaseUser();
        $user->setSupabaseData([
            'id' => 'test-id',
            'user_metadata' => [
                'timezone' => 'America/New_York',
            ],
        ]);
        
        $this->assertEquals('America/New_York', $user->getTimezone());
        
        // Test fallback to config
        $user = new SupabaseUser();
        $user->setSupabaseData([
            'id' => 'test-id',
            'user_metadata' => [],
        ]);
        
        $this->assertEquals('UTC', $user->getTimezone());
    }
    
    public function test_to_array_serialization(): void
    {
        $user = new SupabaseUser([
            'id' => 'test-id',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'remember_token' => 'secret-token',
            'password' => 'secret-password',
        ]);
        
        $user->setSupabaseData([
            'id' => 'test-id',
            'app_metadata' => [
                'role' => 'user',
                'subscription_active' => true,
                'subscription_plan' => 'basic',
            ],
        ]);
        
        $array = $user->toArray();
        
        // Should include computed attributes
        $this->assertArrayHasKey('full_name', $array);
        $this->assertArrayHasKey('initials', $array);
        $this->assertArrayHasKey('avatar', $array);
        $this->assertArrayHasKey('is_admin', $array);
        $this->assertArrayHasKey('is_subscribed', $array);
        $this->assertArrayHasKey('subscription_plan', $array);
        
        // Should exclude sensitive data
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayNotHasKey('password', $array);
        
        // Verify values
        $this->assertEquals('Test User', $array['full_name']);
        $this->assertEquals('TE', $array['initials']);
        $this->assertFalse($array['is_admin']);
        $this->assertTrue($array['is_subscribed']);
        $this->assertEquals('basic', $array['subscription_plan']);
    }
    
    public function test_email_for_password_reset(): void
    {
        $user = new SupabaseUser(['email' => 'reset@example.com']);
        
        $this->assertEquals('reset@example.com', $user->getEmailForPasswordReset());
    }
}