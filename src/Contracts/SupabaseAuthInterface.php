<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Contracts;

interface SupabaseAuthInterface
{
    public function signUp(string $email, string $password, array $data = []): array;
    
    public function signIn(string $email, string $password): array;
    
    public function signOut(string $accessToken): array;
    
    public function refreshToken(string $refreshToken): array;
    
    public function getUser(string $accessToken): array;
    
    public function updateUser(string $accessToken, array $data): array;
    
    public function resetPasswordForEmail(string $email, ?string $redirectTo = null): array;
    
    public function updatePassword(string $accessToken, string $newPassword): array;
    
    public function verifyOtp(string $email, string $token, string $type = 'email'): array;
    
    public function resendOtp(string $email, string $type = 'signup'): array;
    
    public function signInWithOAuth(string $provider, array $options = []): string;
    
    public function verifyToken(string $token): array;
    
    public function getUserById(string $userId): array;
    
    public function deleteUser(string $userId): array;
}