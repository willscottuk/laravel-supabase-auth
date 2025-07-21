<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Supabase\LaravelAuth\Traits\HasSupabaseAuth;

class SupabaseUser extends Authenticatable
{
    use Notifiable, HasSupabaseAuth;
    
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'email',
        'name',
        'avatar_url',
        'phone',
        'email_verified_at',
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    protected ?array $supabaseData = null;
    protected ?string $accessToken = null;
    
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }
    
    public function getAuthIdentifier()
    {
        return $this->id;
    }
    
    public function getAuthPassword(): string
    {
        return '';
    }
    
    public function setSupabaseData(array $data): void
    {
        $this->supabaseData = $data;
        
        if (isset($data['id'])) {
            $this->id = $data['id'];
        }
        
        if (isset($data['email'])) {
            $this->email = $data['email'];
        }
        
        if (isset($data['email_confirmed_at'])) {
            $this->email_verified_at = \Carbon\Carbon::parse($data['email_confirmed_at']);
        }
        
        if (isset($data['user_metadata'])) {
            foreach ($data['user_metadata'] as $key => $value) {
                if ($this->isFillable($key)) {
                    $this->setAttribute($key, $value);
                }
            }
        }
        
        // Handle phone number
        if (isset($data['phone'])) {
            $this->phone = $data['phone'];
        }
        
        // Handle avatar URL
        if (isset($data['user_metadata']['avatar_url'])) {
            $this->avatar_url = $data['user_metadata']['avatar_url'];
        }
    }
    
    public function getSupabaseData(): ?array
    {
        return $this->supabaseData;
    }
    
    public function setAccessToken(?string $token): void
    {
        $this->accessToken = $token;
    }
    
    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }
    
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }
    
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }
    
    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }
    
    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->name) {
            return $this->name;
        }
        
        // Try to get from user metadata
        $metadata = $this->getSupabaseUserMetadata();
        return $metadata['full_name'] ?? $metadata['name'] ?? $this->email;
    }
    
    /**
     * Get the user's initials.
     */
    public function getInitialsAttribute(): string
    {
        $name = $this->getFullNameAttribute();
        $words = explode(' ', $name);
        
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        
        return strtoupper(substr($name, 0, 2));
    }
    
    /**
     * Get the user's avatar URL or generate a default one.
     */
    public function getAvatarAttribute(): string
    {
        if ($this->avatar_url) {
            return $this->avatar_url;
        }
        
        // Generate a default avatar using Gravatar or similar service
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
    }
    
    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        $userRole = $this->getSupabaseRole();
        return $userRole === $role;
    }
    
    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        $userRole = $this->getSupabaseRole();
        return in_array($userRole, $roles);
    }
    
    /**
     * Get the user's subscription status.
     */
    public function isSubscribed(): bool
    {
        $metadata = $this->getSupabaseAppMetadata();
        return $metadata['subscription_active'] ?? false;
    }
    
    /**
     * Get the user's subscription plan.
     */
    public function getSubscriptionPlan(): ?string
    {
        $metadata = $this->getSupabaseAppMetadata();
        return $metadata['subscription_plan'] ?? null;
    }
    
    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasRole('super_admin');
    }
    
    /**
     * Get the user's timezone.
     */
    public function getTimezone(): string
    {
        $metadata = $this->getSupabaseUserMetadata();
        return $metadata['timezone'] ?? config('app.timezone', 'UTC');
    }
    
    /**
     * Serialize the model for JSON.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // Add computed attributes
        $array['full_name'] = $this->getFullNameAttribute();
        $array['initials'] = $this->getInitialsAttribute();
        $array['avatar'] = $this->getAvatarAttribute();
        $array['is_admin'] = $this->isAdmin();
        $array['is_subscribed'] = $this->isSubscribed();
        $array['subscription_plan'] = $this->getSubscriptionPlan();
        
        // Remove sensitive data
        unset($array['remember_token'], $array['password']);
        
        return $array;
    }
}