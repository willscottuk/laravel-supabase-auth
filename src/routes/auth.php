<?php

use Illuminate\Support\Facades\Route;
use YourVendor\LaravelSupabaseAuth\Http\Controllers\AuthController;

Route::prefix('auth/supabase')->name('supabase.auth.')->group(function () {
    
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
    
    Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');
    Route::post('/password/update', [AuthController::class, 'updatePassword'])
        ->middleware('auth:supabase')
        ->name('password.update');
    
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->name('otp.verify');
    Route::post('/otp/resend', [AuthController::class, 'resendOtp'])->name('otp.resend');
    
    Route::get('/user', [AuthController::class, 'user'])
        ->middleware('auth:supabase')
        ->name('user');
    
    Route::get('/callback', function () {
        return redirect('/dashboard');
    })->name('callback');
    
});