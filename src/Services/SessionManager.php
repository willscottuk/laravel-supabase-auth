<?php

namespace YourVendor\LaravelSupabaseAuth\Services;

use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;

class SessionManager
{
    protected Store $session;
    protected SupabaseAuth $supabase;
    
    public function __construct(Store $session, SupabaseAuth $supabase)
    {
        $this->session = $session;
        $this->supabase = $supabase;
    }
    
    public function storeUserSession(array $userData, string $accessToken, string $refreshToken = null)
    {
        $this->session->put('supabase_user', $userData);
        $this->session->put('supabase_access_token', $accessToken);
        
        if ($refreshToken) {
            $this->session->put('supabase_refresh_token', $refreshToken);
        }
        
        $this->session->put('supabase_session_expires_at', time() + 3600);
        
        $this->session->regenerate();
    }
    
    public function getUserFromSession()
    {
        return $this->session->get('supabase_user');
    }
    
    public function getAccessToken()
    {
        return $this->session->get('supabase_access_token');
    }
    
    public function getRefreshToken()
    {
        return $this->session->get('supabase_refresh_token');
    }
    
    public function isSessionExpired()
    {
        $expiresAt = $this->session->get('supabase_session_expires_at');
        
        if (!$expiresAt) {
            return true;
        }
        
        return time() >= $expiresAt;
    }
    
    public function refreshSession()
    {
        $refreshToken = $this->getRefreshToken();
        
        if (!$refreshToken) {
            return false;
        }
        
        try {
            $response = $this->supabase->refreshToken($refreshToken);
            
            if (isset($response['access_token']) && isset($response['user'])) {
                $this->storeUserSession(
                    $response['user'],
                    $response['access_token'],
                    $response['refresh_token'] ?? $refreshToken
                );
                
                return true;
            }
        } catch (\Exception $e) {
            $this->clearSession();
        }
        
        return false;
    }
    
    public function clearSession()
    {
        $this->session->forget([
            'supabase_user',
            'supabase_access_token',
            'supabase_refresh_token',
            'supabase_session_expires_at',
        ]);
        
        $this->session->regenerate();
    }
    
    public function extendSession(int $seconds = 3600)
    {
        $this->session->put('supabase_session_expires_at', time() + $seconds);
    }
    
    public function getSessionData()
    {
        return [
            'user' => $this->getUserFromSession(),
            'access_token' => $this->getAccessToken(),
            'refresh_token' => $this->getRefreshToken(),
            'expires_at' => $this->session->get('supabase_session_expires_at'),
            'is_expired' => $this->isSessionExpired(),
        ];
    }
    
    public function validateSession()
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return false;
        }
        
        if ($this->isSessionExpired()) {
            return $this->refreshSession();
        }
        
        $tokenValidation = $this->supabase->verifyToken($accessToken);
        
        if (!$tokenValidation['valid']) {
            return $this->refreshSession();
        }
        
        return true;
    }
    
    public function syncWithAuthGuard()
    {
        if (!$this->validateSession()) {
            Auth::logout();
            return false;
        }
        
        $userData = $this->getUserFromSession();
        $accessToken = $this->getAccessToken();
        
        if ($userData && $accessToken) {
            $userProvider = Auth::createUserProvider('supabase');
            $user = $userProvider->retrieveById($userData['id']);
            
            if (!$user) {
                $user = $userProvider->createFromSupabase($userData);
            }
            
            $user->setSupabaseData($userData);
            $user->setAccessToken($accessToken);
            
            Auth::setUser($user);
            
            return true;
        }
        
        return false;
    }
}