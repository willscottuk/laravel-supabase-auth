<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Traits;

use Supabase\LaravelAuth\Services\SupabaseAuth;
use Supabase\LaravelAuth\Contracts\SupabaseAuthInterface;

trait HasSupabaseAuth
{
    protected ?string $supabaseAccessToken = null;
    protected ?array $supabaseData = null;
    
    public function getSupabaseAuth(): SupabaseAuthInterface
    {
        return app(SupabaseAuthInterface::class);
    }
    
    public function updateSupabaseProfile(array $data): array
    {
        if (!$this->supabaseAccessToken) {
            throw new \Exception('No access token available for Supabase API calls');
        }
        
        $response = $this->getSupabaseAuth()->updateUser($this->supabaseAccessToken, $data);
        
        // Update local model if successful
        if (isset($response['id'])) {
            $this->setSupabaseData($response);
            $this->save();
        }
        
        return $response;
    }
    
    public function changeSupabasePassword(string $newPassword): array
    {
        if (!$this->supabaseAccessToken) {
            throw new \Exception('No access token available for Supabase API calls');
        }
        
        return $this->getSupabaseAuth()->updatePassword($this->supabaseAccessToken, $newPassword);
    }
    
    public function getSupabaseUserId(): string
    {
        return $this->supabaseData['id'] ?? $this->id ?? '';
    }
    
    public function getSupabaseUserMetadata(): array
    {
        return $this->supabaseData['user_metadata'] ?? [];
    }
    
    public function getSupabaseAppMetadata(): array
    {
        return $this->supabaseData['app_metadata'] ?? [];
    }
    
    public function isEmailConfirmed(): bool
    {
        return !empty($this->supabaseData['email_confirmed_at']);
    }
    
    public function isPhoneConfirmed(): bool
    {
        return !empty($this->supabaseData['phone_confirmed_at']);
    }
    
    public function getSupabaseRole(): ?string
    {
        $appMetadata = $this->getSupabaseAppMetadata();
        return $appMetadata['role'] ?? null;
    }
    
    public function getSupabaseProvider(): string
    {
        $appMetadata = $this->getSupabaseAppMetadata();
        return $appMetadata['provider'] ?? 'email';
    }
    
    public function getSupabaseProviders(): array
    {
        $appMetadata = $this->getSupabaseAppMetadata();
        return $appMetadata['providers'] ?? [];
    }
    
    public function getLastSignInAt(): ?\Carbon\Carbon
    {
        if (isset($this->supabaseData['last_sign_in_at'])) {
            return \Carbon\Carbon::parse($this->supabaseData['last_sign_in_at']);
        }
        
        return null;
    }
    
    public function getEmailConfirmedAt(): ?\Carbon\Carbon
    {
        if (isset($this->supabaseData['email_confirmed_at'])) {
            return \Carbon\Carbon::parse($this->supabaseData['email_confirmed_at']);
        }
        
        return null;
    }
    
    public function getPhoneConfirmedAt(): ?\Carbon\Carbon
    {
        if (isset($this->supabaseData['phone_confirmed_at'])) {
            return \Carbon\Carbon::parse($this->supabaseData['phone_confirmed_at']);
        }
        
        return null;
    }
    
    public function getSupabaseCreatedAt(): ?\Carbon\Carbon
    {
        if (isset($this->supabaseData['created_at'])) {
            return \Carbon\Carbon::parse($this->supabaseData['created_at']);
        }
        
        return null;
    }
    
    public function getSupabaseUpdatedAt(): ?\Carbon\Carbon
    {
        if (isset($this->supabaseData['updated_at'])) {
            return \Carbon\Carbon::parse($this->supabaseData['updated_at']);
        }
        
        return null;
    }
    
    /**
     * Check if the user account is active.
     */
    public function isActive(): bool
    {
        $appMetadata = $this->getSupabaseAppMetadata();
        return $appMetadata['active'] ?? true;
    }
    
    /**
     * Check if the user account is banned.
     */
    public function isBanned(): bool
    {
        $appMetadata = $this->getSupabaseAppMetadata();
        return $appMetadata['banned'] ?? false;
    }
    
    /**
     * Get the user's custom claims.
     */
    public function getCustomClaims(): array
    {
        $appMetadata = $this->getSupabaseAppMetadata();
        return $appMetadata['claims'] ?? [];
    }
    
    /**
     * Check if the user has a specific custom claim.
     */
    public function hasClaim(string $claim, $value = true): bool
    {
        $claims = $this->getCustomClaims();
        return isset($claims[$claim]) && $claims[$claim] === $value;
    }
    
    /**
     * Get user preferences from metadata.
     */
    public function getPreferences(): array
    {
        $userMetadata = $this->getSupabaseUserMetadata();
        return $userMetadata['preferences'] ?? [];
    }
    
    /**
     * Get a specific user preference.
     */
    public function getPreference(string $key, $default = null)
    {
        $preferences = $this->getPreferences();
        return $preferences[$key] ?? $default;
    }
    
    /**
     * Update user preferences.
     */
    public function updatePreferences(array $preferences): array
    {
        $currentMetadata = $this->getSupabaseUserMetadata();
        $currentMetadata['preferences'] = array_merge(
            $currentMetadata['preferences'] ?? [],
            $preferences
        );
        
        return $this->updateSupabaseProfile(['data' => $currentMetadata]);
    }
    
    /**
     * Get the user's avatar URL from Supabase metadata.
     */
    public function getSupabaseAvatar(): ?string
    {
        $userMetadata = $this->getSupabaseUserMetadata();
        return $userMetadata['avatar_url'] ?? null;
    }
    
    /**
     * Get the user's full name from Supabase metadata.
     */
    public function getSupabaseFullName(): ?string
    {
        $userMetadata = $this->getSupabaseUserMetadata();
        return $userMetadata['full_name'] ?? $userMetadata['name'] ?? null;
    }
    
    /**
     * Check if the user signed up via OAuth.
     */
    public function isOAuthUser(): bool
    {
        return $this->getSupabaseProvider() !== 'email';
    }
    
    /**
     * Get OAuth provider info if applicable.
     */
    public function getOAuthProviderInfo(): ?array
    {
        if (!$this->isOAuthUser()) {
            return null;
        }
        
        $identities = $this->supabaseData['identities'] ?? [];
        
        foreach ($identities as $identity) {
            if ($identity['provider'] !== 'email') {
                return $identity;
            }
        }
        
        return null;
    }
    
    /**
     * Get all linked identities.
     */
    public function getLinkedIdentities(): array
    {
        return $this->supabaseData['identities'] ?? [];
    }
    
    /**
     * Check if user has multiple authentication methods.
     */
    public function hasMultipleIdentities(): bool
    {
        return count($this->getLinkedIdentities()) > 1;
    }
}