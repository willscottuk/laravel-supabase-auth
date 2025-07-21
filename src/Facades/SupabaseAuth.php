<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array signUp(string $email, string $password, array $data = [])
 * @method static array signIn(string $email, string $password)
 * @method static array signOut(string $accessToken)
 * @method static array refreshToken(string $refreshToken)
 * @method static array getUser(string $accessToken)
 * @method static array updateUser(string $accessToken, array $data)
 * @method static array resetPasswordForEmail(string $email, string $redirectTo = null)
 * @method static array updatePassword(string $accessToken, string $newPassword)
 * @method static array verifyOtp(string $email, string $token, string $type = 'email')
 * @method static array resendOtp(string $email, string $type = 'signup')
 * @method static string signInWithOAuth(string $provider, array $options = [])
 * @method static array verifyToken(string $token)
 * @method static array getUserById(string $userId)
 * @method static array deleteUser(string $userId)
 *
 * @see \Supabase\LaravelAuth\Services\SupabaseAuth
 */
class SupabaseAuth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Supabase\LaravelAuth\Contracts\SupabaseAuthInterface::class;
    }
}