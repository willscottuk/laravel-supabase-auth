<?php

namespace YourVendor\LaravelSupabaseAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use YourVendor\LaravelSupabaseAuth\Services\SupabaseAuth;

class EnsureTokenIsValid
{
    protected SupabaseAuth $supabase;
    
    public function __construct(SupabaseAuth $supabase)
    {
        $this->supabase = $supabase;
    }
    
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user || !$user->getAccessToken()) {
            return $this->unauthorized();
        }
        
        $tokenValidation = $this->supabase->verifyToken($user->getAccessToken());
        
        if (!$tokenValidation['valid']) {
            $guard = Auth::guard('supabase');
            
            if ($guard->refreshAccessToken()) {
                $user = $guard->user();
                
                if ($user && $user->getAccessToken()) {
                    $tokenValidation = $this->supabase->verifyToken($user->getAccessToken());
                    
                    if ($tokenValidation['valid']) {
                        return $next($request);
                    }
                }
            }
            
            Auth::logout();
            return $this->unauthorized();
        }
        
        return $next($request);
    }
    
    protected function unauthorized()
    {
        return response()->json([
            'error' => 'Token invalid or expired',
            'message' => 'Please login again.',
        ], 401);
    }
}