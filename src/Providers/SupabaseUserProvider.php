<?php

namespace YourVendor\LaravelSupabaseAuth\Providers;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use YourVendor\LaravelSupabaseAuth\Services\SupabaseAuth;

class SupabaseUserProvider implements UserProvider
{
    protected SupabaseAuth $supabase;
    protected string $model;
    
    public function __construct(SupabaseAuth $supabase, string $model)
    {
        $this->supabase = $supabase;
        $this->model = $model;
    }
    
    public function retrieveById($identifier)
    {
        $model = $this->createModel();
        
        return $model->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();
    }
    
    public function retrieveByToken($identifier, $token)
    {
        $model = $this->createModel();
        
        $retrievedModel = $model->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->where($model->getRememberTokenName(), $token)
            ->first();
        
        return $retrievedModel;
    }
    
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $user->setRememberToken($token);
        
        $timestamps = $user->timestamps;
        $user->timestamps = false;
        
        $user->save();
        
        $user->timestamps = $timestamps;
    }
    
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) || !isset($credentials['email'])) {
            return null;
        }
        
        $query = $this->newModelQuery();
        
        foreach ($credentials as $key => $value) {
            if (!\Illuminate\Support\Str::contains($key, 'password')) {
                $query->where($key, $value);
            }
        }
        
        return $query->first();
    }
    
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (!isset($credentials['email']) || !isset($credentials['password'])) {
            return false;
        }
        
        try {
            $response = $this->supabase->signIn($credentials['email'], $credentials['password']);
            return isset($response['access_token']);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function createFromSupabase(array $userData)
    {
        $model = $this->createModel();
        
        $model->forceFill([
            'id' => $userData['id'],
            'email' => $userData['email'],
            'email_verified_at' => isset($userData['email_confirmed_at']) 
                ? \Carbon\Carbon::parse($userData['email_confirmed_at']) 
                : null,
            'created_at' => isset($userData['created_at']) 
                ? \Carbon\Carbon::parse($userData['created_at']) 
                : now(),
            'updated_at' => isset($userData['updated_at']) 
                ? \Carbon\Carbon::parse($userData['updated_at']) 
                : now(),
        ]);
        
        if (isset($userData['user_metadata'])) {
            foreach ($userData['user_metadata'] as $key => $value) {
                if ($model->isFillable($key)) {
                    $model->setAttribute($key, $value);
                }
            }
        }
        
        $model->save();
        
        return $model;
    }
    
    public function createModel()
    {
        $class = '\\' . ltrim($this->model, '\\');
        
        return new $class;
    }
    
    public function getModel()
    {
        return $this->model;
    }
    
    public function setModel($model)
    {
        $this->model = $model;
        
        return $this;
    }
    
    protected function newModelQuery()
    {
        return $this->createModel()->newQuery();
    }
}