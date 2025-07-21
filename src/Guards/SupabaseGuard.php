<?php

namespace YourVendor\LaravelSupabaseAuth\Guards;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use YourVendor\LaravelSupabaseAuth\Services\SupabaseAuth;
use Symfony\Component\HttpFoundation\Response;

class SupabaseGuard implements Guard
{
    use GuardHelpers;
    
    protected string $name;
    protected Session $session;
    protected CookieJar $cookie;
    protected Request $request;
    protected SupabaseAuth $supabase;
    protected ?array $lastAttempted = null;
    protected bool $loggedOut = false;
    
    public function __construct(
        string $name,
        UserProvider $provider,
        Session $session,
        SupabaseAuth $supabase
    ) {
        $this->name = $name;
        $this->provider = $provider;
        $this->session = $session;
        $this->supabase = $supabase;
    }
    
    public function user()
    {
        if ($this->loggedOut) {
            return null;
        }
        
        if (!is_null($this->user)) {
            return $this->user;
        }
        
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return null;
        }
        
        try {
            $userData = $this->supabase->getUser($accessToken);
            
            if (isset($userData['id'])) {
                $this->user = $this->provider->retrieveById($userData['id']);
                
                if ($this->user) {
                    $this->user->setSupabaseData($userData);
                    $this->user->setAccessToken($accessToken);
                }
            }
        } catch (\Exception $e) {
            $this->clearUserDataFromStorage();
            return null;
        }
        
        return $this->user;
    }
    
    public function validate(array $credentials = [])
    {
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return false;
        }
        
        try {
            $response = $this->supabase->signIn($credentials['email'], $credentials['password']);
            return isset($response['access_token']);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function attempt(array $credentials = [], $remember = false)
    {
        $this->lastAttempted = $credentials;
        
        if (!$this->validate($credentials)) {
            return false;
        }
        
        try {
            $response = $this->supabase->signIn($credentials['email'], $credentials['password']);
            
            if (isset($response['access_token']) && isset($response['user'])) {
                $user = $this->provider->retrieveById($response['user']['id']);
                
                if (!$user) {
                    $user = $this->provider->createFromSupabase($response['user']);
                }
                
                $user->setSupabaseData($response['user']);
                $user->setAccessToken($response['access_token']);
                
                $this->login($user, $remember);
                
                if (isset($response['refresh_token'])) {
                    $this->storeRefreshToken($response['refresh_token']);
                }
                
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
        
        return false;
    }
    
    public function login(Authenticatable $user, $remember = false)
    {
        $this->updateSession($user->getAuthIdentifier());
        
        if ($user->getAccessToken()) {
            $this->storeAccessToken($user->getAccessToken());
        }
        
        if ($remember) {
            $this->ensureRememberTokenIsSet($user);
            $this->queueRecallerCookie($user);
        }
        
        $this->fireLoginEvent($user, $remember);
        
        $this->setUser($user);
    }
    
    public function logout()
    {
        $user = $this->user();
        
        if ($user && $user->getAccessToken()) {
            try {
                $this->supabase->signOut($user->getAccessToken());
            } catch (\Exception $e) {
                // Log error but continue with local logout
            }
        }
        
        $this->clearUserDataFromStorage();
        
        if (!is_null($this->user)) {
            $this->cycleRememberToken($user);
        }
        
        if (isset($this->events)) {
            $this->events->dispatch(new \Illuminate\Auth\Events\Logout($this->name, $user));
        }
        
        $this->user = null;
        $this->loggedOut = true;
    }
    
    public function loginUsingId($id, $remember = false)
    {
        if (!is_null($user = $this->provider->retrieveById($id))) {
            $this->login($user, $remember);
            return $user;
        }
        
        return false;
    }
    
    public function onceUsingId($id)
    {
        if (!is_null($user = $this->provider->retrieveById($id))) {
            $this->setUser($user);
            return $user;
        }
        
        return false;
    }
    
    public function viaRemember()
    {
        return false;
    }
    
    protected function updateSession($id)
    {
        $this->session->put($this->getName(), $id);
        $this->session->migrate(true);
    }
    
    protected function getName()
    {
        return 'login_supabase_' . sha1(static::class);
    }
    
    protected function getAccessToken()
    {
        return $this->session->get($this->getAccessTokenName());
    }
    
    protected function storeAccessToken($token)
    {
        $this->session->put($this->getAccessTokenName(), $token);
    }
    
    protected function getAccessTokenName()
    {
        return 'supabase_access_token_' . $this->name;
    }
    
    protected function storeRefreshToken($token)
    {
        $this->session->put($this->getRefreshTokenName(), $token);
    }
    
    protected function getRefreshToken()
    {
        return $this->session->get($this->getRefreshTokenName());
    }
    
    protected function getRefreshTokenName()
    {
        return 'supabase_refresh_token_' . $this->name;
    }
    
    protected function clearUserDataFromStorage()
    {
        $this->session->remove($this->getName());
        $this->session->remove($this->getAccessTokenName());
        $this->session->remove($this->getRefreshTokenName());
    }
    
    protected function ensureRememberTokenIsSet(Authenticatable $user)
    {
        if (empty($user->getRememberToken())) {
            $this->cycleRememberToken($user);
        }
    }
    
    protected function queueRecallerCookie(Authenticatable $user)
    {
        $this->getCookieJar()->queue($this->createRecaller(
            $user->getAuthIdentifier() . '|' . $user->getRememberToken() . '|' . $user->getAuthPassword()
        ));
    }
    
    protected function createRecaller($value)
    {
        return $this->getCookieJar()->forever($this->getRecallerName(), $value);
    }
    
    protected function getRecallerName()
    {
        return 'remember_supabase_' . $this->name;
    }
    
    protected function cycleRememberToken(Authenticatable $user)
    {
        $user->setRememberToken($token = \Illuminate\Support\Str::random(60));
        $this->provider->updateRememberToken($user, $token);
    }
    
    public function setCookieJar(CookieJar $cookie)
    {
        $this->cookie = $cookie;
    }
    
    public function getCookieJar()
    {
        if (!isset($this->cookie)) {
            throw new \RuntimeException('Cookie jar has not been set.');
        }
        
        return $this->cookie;
    }
    
    public function setDispatcher($events)
    {
        $this->events = $events;
    }
    
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }
    
    protected function fireLoginEvent($user, $remember = false)
    {
        if (isset($this->events)) {
            $this->events->dispatch(new \Illuminate\Auth\Events\Login($this->name, $user, $remember));
        }
    }
    
    public function refreshAccessToken()
    {
        $refreshToken = $this->getRefreshToken();
        
        if (!$refreshToken) {
            return false;
        }
        
        try {
            $response = $this->supabase->refreshToken($refreshToken);
            
            if (isset($response['access_token'])) {
                $this->storeAccessToken($response['access_token']);
                
                if (isset($response['refresh_token'])) {
                    $this->storeRefreshToken($response['refresh_token']);
                }
                
                return true;
            }
        } catch (\Exception $e) {
            $this->clearUserDataFromStorage();
        }
        
        return false;
    }
}