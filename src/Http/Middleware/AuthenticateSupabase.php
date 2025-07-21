<?php

namespace YourVendor\LaravelSupabaseAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateSupabase
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);
        
        return $next($request);
    }
    
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }
        
        foreach ($guards as $guard) {
            if ($this->auth()->guard($guard)->check()) {
                return $this->auth()->shouldUse($guard);
            }
        }
        
        $this->unauthenticated($request, $guards);
    }
    
    protected function unauthenticated($request, array $guards)
    {
        if ($request->expectsJson()) {
            abort(401, 'Unauthenticated');
        }
        
        throw new \Illuminate\Auth\AuthenticationException(
            'Unauthenticated.', $guards, $this->redirectTo($request)
        );
    }
    
    protected function redirectTo($request)
    {
        if (!$request->expectsJson()) {
            return route('login');
        }
    }
    
    protected function auth()
    {
        return Auth::getFacadeRoot();
    }
}