<?php

namespace YourVendor\LaravelSupabaseAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Mockery;
use YourVendor\LaravelSupabaseAuth\Guards\SupabaseGuard;
use YourVendor\LaravelSupabaseAuth\Services\SupabaseAuth;
use YourVendor\LaravelSupabaseAuth\Providers\SupabaseUserProvider;
use Illuminate\Contracts\Session\Session;

class SupabaseGuardTest extends TestCase
{
    protected $guard;
    protected $provider;
    protected $session;
    protected $supabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->provider = Mockery::mock(SupabaseUserProvider::class);
        $this->session = Mockery::mock(Session::class);
        $this->supabase = Mockery::mock(SupabaseAuth::class);
        
        $this->guard = new SupabaseGuard(
            'supabase',
            $this->provider,
            $this->session,
            $this->supabase
        );
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function test_validate_returns_false_with_empty_credentials()
    {
        $result = $this->guard->validate([]);
        
        $this->assertFalse($result);
    }
    
    public function test_validate_returns_false_with_missing_email()
    {
        $result = $this->guard->validate(['password' => 'secret']);
        
        $this->assertFalse($result);
    }
    
    public function test_validate_returns_false_with_missing_password()
    {
        $result = $this->guard->validate(['email' => 'test@example.com']);
        
        $this->assertFalse($result);
    }
    
    public function test_validate_returns_true_with_valid_credentials()
    {
        $this->supabase->shouldReceive('signIn')
            ->once()
            ->with('test@example.com', 'password')
            ->andReturn(['access_token' => 'valid-token']);
        
        $result = $this->guard->validate([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        
        $this->assertTrue($result);
    }
    
    public function test_validate_returns_false_with_invalid_credentials()
    {
        $this->supabase->shouldReceive('signIn')
            ->once()
            ->with('test@example.com', 'wrong-password')
            ->andThrow(new \Exception('Invalid credentials'));
        
        $result = $this->guard->validate([
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);
        
        $this->assertFalse($result);
    }
    
    public function test_get_access_token_name_returns_correct_name()
    {
        $reflection = new \ReflectionClass($this->guard);
        $method = $reflection->getMethod('getAccessTokenName');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->guard);
        
        $this->assertEquals('supabase_access_token_supabase', $result);
    }
    
    public function test_get_refresh_token_name_returns_correct_name()
    {
        $reflection = new \ReflectionClass($this->guard);
        $method = $reflection->getMethod('getRefreshTokenName');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->guard);
        
        $this->assertEquals('supabase_refresh_token_supabase', $result);
    }
}