<?php

namespace YourVendor\LaravelSupabaseAuth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use YourVendor\LaravelSupabaseAuth\Services\SupabaseAuth;

class AuthController extends Controller
{
    protected SupabaseAuth $supabase;
    
    public function __construct(SupabaseAuth $supabase)
    {
        $this->supabase = $supabase;
    }
    
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
            'name' => 'sometimes|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }
        
        try {
            $userData = [
                'email' => $request->email,
                'password' => $request->password,
            ];
            
            if ($request->has('name')) {
                $userData['data'] = ['name' => $request->name];
            }
            
            $response = $this->supabase->signUp(
                $request->email,
                $request->password,
                $userData['data'] ?? []
            );
            
            if (isset($response['user'])) {
                return response()->json([
                    'message' => 'Registration successful. Please check your email for verification.',
                    'user' => $response['user'],
                ]);
            }
            
            return response()->json([
                'error' => 'Registration failed',
                'message' => $response['error'] ?? 'Unknown error occurred',
            ], 400);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Registration failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }
        
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');
        
        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();
            
            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'access_token' => $user->getAccessToken(),
            ]);
        }
        
        return response()->json([
            'error' => 'Invalid credentials',
            'message' => 'The provided credentials are incorrect.',
        ], 401);
    }
    
    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
    
    public function user(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }
        
        return response()->json([
            'user' => $user,
        ]);
    }
    
    public function refresh(Request $request)
    {
        $guard = Auth::guard('supabase');
        
        if ($guard->refreshAccessToken()) {
            $user = $guard->user();
            
            return response()->json([
                'message' => 'Token refreshed successfully',
                'user' => $user,
                'access_token' => $user->getAccessToken(),
            ]);
        }
        
        return response()->json([
            'error' => 'Token refresh failed',
            'message' => 'Unable to refresh the access token.',
        ], 401);
    }
    
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }
        
        try {
            $redirectTo = $request->input('redirect_to', config('supabase-auth.password_reset.redirect_url'));
            
            $response = $this->supabase->resetPasswordForEmail($request->email, $redirectTo);
            
            return response()->json([
                'message' => 'Password reset email sent successfully.',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Password reset failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:6',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }
        
        $user = Auth::user();
        
        if (!$user || !$user->getAccessToken()) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }
        
        try {
            $response = $this->supabase->updatePassword($user->getAccessToken(), $request->password);
            
            return response()->json([
                'message' => 'Password updated successfully',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Password update failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required',
            'type' => 'sometimes|string|in:email,sms,phone_change',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }
        
        try {
            $response = $this->supabase->verifyOtp(
                $request->email,
                $request->token,
                $request->input('type', 'email')
            );
            
            if (isset($response['access_token']) && isset($response['user'])) {
                $userProvider = Auth::createUserProvider('supabase');
                $user = $userProvider->retrieveById($response['user']['id']);
                
                if (!$user) {
                    $user = $userProvider->createFromSupabase($response['user']);
                }
                
                $user->setSupabaseData($response['user']);
                $user->setAccessToken($response['access_token']);
                
                Auth::login($user);
                
                return response()->json([
                    'message' => 'OTP verification successful',
                    'user' => $user,
                    'access_token' => $response['access_token'],
                ]);
            }
            
            return response()->json([
                'message' => 'OTP verification successful',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'OTP verification failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
    
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'type' => 'sometimes|string|in:signup,recovery,email_change,phone_change',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors(),
            ], 422);
        }
        
        try {
            $response = $this->supabase->resendOtp(
                $request->email,
                $request->input('type', 'signup')
            );
            
            return response()->json([
                'message' => 'OTP resent successfully',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'OTP resend failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}