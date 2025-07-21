<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
use Supabase\LaravelAuth\Contracts\SupabaseAuthInterface;

class SupabaseAuth implements SupabaseAuthInterface
{
    private SupabaseClient $client;
    private CacheManager $cache;
    private LoggerInterface $logger;
    
    public function __construct(
        SupabaseClient $client,
        CacheManager $cache,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->cache = $cache;
        $this->logger = $logger;
    }
    
    public function signUp(string $email, string $password, array $data = []): array
    {
        $this->logger->info('User registration attempt', [
            'email' => $this->maskEmail($email),
            'has_metadata' => !empty($data),
        ]);
        
        try {
            $payload = [
                'email' => $email,
                'password' => $password,
            ];
            
            if (!empty($data)) {
                $payload['data'] = $data;
            }
            
            $response = $this->client->request('POST', '/auth/v1/signup', [
                'json' => $payload,
            ]);
            
            $this->logger->info('User registration successful', [
                'email' => $this->maskEmail($email),
                'user_id' => $response['user']['id'] ?? 'unknown',
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('User registration failed', [
                'email' => $this->maskEmail($email),
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function signIn(string $email, string $password): array
    {
        $this->logger->info('User login attempt', [
            'email' => $this->maskEmail($email),
        ]);
        
        try {
            $response = $this->client->request('POST', '/auth/v1/token', [
                'json' => [
                    'email' => $email,
                    'password' => $password,
                ],
                'query' => [
                    'grant_type' => 'password',
                ],
            ]);
            
            if (isset($response['user']['id'])) {
                $this->cache->cacheUserData($response['user']['id'], $response['user']);
                
                $this->logger->info('User login successful', [
                    'email' => $this->maskEmail($email),
                    'user_id' => $response['user']['id'],
                ]);
            }
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('User login failed', [
                'email' => $this->maskEmail($email),
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function signOut(string $accessToken): array
    {
        try {
            $response = $this->client->request('POST', '/auth/v1/logout', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
            
            $this->logger->info('User logout successful');
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('User logout failed', [
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $this->logger->info('Token refresh attempt');
        
        try {
            $response = $this->client->request('POST', '/auth/v1/token', [
                'json' => [
                    'refresh_token' => $refreshToken,
                ],
                'query' => [
                    'grant_type' => 'refresh_token',
                ],
            ]);
            
            if (isset($response['user']['id'])) {
                $this->cache->cacheUserData($response['user']['id'], $response['user']);
            }
            
            $this->logger->info('Token refresh successful');
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Token refresh failed', [
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function getUser(string $accessToken): array
    {
        try {
            $response = $this->client->request('GET', '/auth/v1/user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);
            
            if (isset($response['id'])) {
                $this->cache->cacheUserData($response['id'], $response);
            }
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Get user failed', [
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function updateUser(string $accessToken, array $data): array
    {
        $this->logger->info('User update attempt', [
            'fields' => array_keys($data),
        ]);
        
        try {
            $response = $this->client->request('PUT', '/auth/v1/user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => $data,
            ]);
            
            if (isset($response['id'])) {
                $this->cache->invalidateUserCache($response['id']);
                $this->cache->cacheUserData($response['id'], $response);
                
                $this->logger->info('User update successful', [
                    'user_id' => $response['id'],
                ]);
            }
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('User update failed', [
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function resetPasswordForEmail(string $email, ?string $redirectTo = null): array
    {
        $this->logger->info('Password reset request', [
            'email' => $this->maskEmail($email),
        ]);
        
        try {
            $data = ['email' => $email];
            
            if ($redirectTo) {
                $data['redirectTo'] = $redirectTo;
            }
            
            $response = $this->client->request('POST', '/auth/v1/recover', [
                'json' => $data,
            ]);
            
            $this->logger->info('Password reset email sent', [
                'email' => $this->maskEmail($email),
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Password reset failed', [
                'email' => $this->maskEmail($email),
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function updatePassword(string $accessToken, string $newPassword): array
    {
        $this->logger->info('Password update attempt');
        
        try {
            $response = $this->client->request('PUT', '/auth/v1/user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
                'json' => [
                    'password' => $newPassword,
                ],
            ]);
            
            $this->logger->info('Password update successful');
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Password update failed', [
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function verifyOtp(string $email, string $token, string $type = 'email'): array
    {
        $this->logger->info('OTP verification attempt', [
            'email' => $this->maskEmail($email),
            'type' => $type,
        ]);
        
        try {
            $response = $this->client->request('POST', '/auth/v1/verify', [
                'json' => [
                    'email' => $email,
                    'token' => $token,
                    'type' => $type,
                ],
            ]);
            
            if (isset($response['user']['id'])) {
                $this->cache->cacheUserData($response['user']['id'], $response['user']);
            }
            
            $this->logger->info('OTP verification successful', [
                'email' => $this->maskEmail($email),
                'type' => $type,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('OTP verification failed', [
                'email' => $this->maskEmail($email),
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function resendOtp(string $email, string $type = 'signup'): array
    {
        $this->logger->info('OTP resend request', [
            'email' => $this->maskEmail($email),
            'type' => $type,
        ]);
        
        try {
            $response = $this->client->request('POST', '/auth/v1/otp', [
                'json' => [
                    'email' => $email,
                    'type' => $type,
                ],
            ]);
            
            $this->logger->info('OTP resent successfully', [
                'email' => $this->maskEmail($email),
                'type' => $type,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('OTP resend failed', [
                'email' => $this->maskEmail($email),
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function signInWithOAuth(string $provider, array $options = []): string
    {
        $this->logger->info('OAuth login attempt', [
            'provider' => $provider,
        ]);
        
        $query = array_merge([
            'provider' => $provider,
        ], $options);
        
        $url = $this->client->getUrl() . '/auth/v1/authorize?' . http_build_query($query);
        
        $this->logger->info('OAuth URL generated', [
            'provider' => $provider,
        ]);
        
        return $url;
    }
    
    public function verifyToken(string $token): array
    {
        try {
            // Check cache first
            $tokenHash = hash('sha256', $token);
            $cached = $this->cache->getCachedJwtValidation($tokenHash);
            
            if ($cached !== null) {
                return $cached;
            }
            
            $secret = config('supabase-auth.jwt.secret');
            $algorithm = config('supabase-auth.jwt.algorithm', 'HS256');
            $leeway = config('supabase-auth.jwt.leeway', 60);
            
            JWT::$leeway = $leeway;
            
            $decoded = JWT::decode($token, new Key($secret, $algorithm));
            
            $result = [
                'valid' => true,
                'payload' => (array) $decoded,
                'expires_at' => $decoded->exp ?? null,
            ];
            
            // Cache the result
            $this->cache->cacheJwtValidation($tokenHash, $result);
            
            return $result;
            
        } catch (\Exception $e) {
            $result = [
                'valid' => false,
                'error' => $e->getMessage(),
            ];
            
            $this->logger->warning('JWT token validation failed', [
                'error' => $e->getMessage(),
            ]);
            
            return $result;
        }
    }
    
    public function getUserById(string $userId): array
    {
        try {
            // Check cache first
            $cached = $this->cache->getCachedUserData($userId);
            if ($cached !== null) {
                return $cached;
            }
            
            $response = $this->client->request('GET', "/auth/v1/admin/users/{$userId}", [], true);
            
            $this->cache->cacheUserData($userId, $response);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Get user by ID failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function deleteUser(string $userId): array
    {
        $this->logger->info('User deletion attempt', [
            'user_id' => $userId,
        ]);
        
        try {
            $response = $this->client->request('DELETE', "/auth/v1/admin/users/{$userId}", [], true);
            
            // Clear cache
            $this->cache->invalidateUserCache($userId);
            
            $this->logger->info('User deletion successful', [
                'user_id' => $userId,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('User deletion failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        
        if (count($parts) !== 2) {
            return 'invalid@email.com';
        }
        
        $username = $parts[0];
        $domain = $parts[1];
        
        $maskedUsername = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 2));
        
        return $maskedUsername . '@' . $domain;
    }
}