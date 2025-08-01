<?php

namespace YourVendor\LaravelSupabaseAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;
        
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Already authenticated',
                    ], 200);
                }
                
                return redirect(config('supabase-auth.auth.provider_redirect', '/dashboard'));
            }
        }
        
        return $next($request);
    }
}