<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Supabase\LaravelAuth\Guards\SupabaseGuard;
use Supabase\LaravelAuth\Providers\SupabaseUserProvider;
use Supabase\LaravelAuth\Services\SupabaseClient;
use Supabase\LaravelAuth\Services\SupabaseAuth;
use Supabase\LaravelAuth\Services\ConfigurationValidator;
use Supabase\LaravelAuth\Services\CircuitBreaker;
use Supabase\LaravelAuth\Services\RateLimiter;
use Supabase\LaravelAuth\Services\CacheManager;
use Supabase\LaravelAuth\Contracts\SupabaseAuthInterface;
use Supabase\LaravelAuth\Contracts\CircuitBreakerInterface;
use Supabase\LaravelAuth\Contracts\RateLimiterInterface;
use Supabase\LaravelAuth\Exceptions\ConfigurationException;

class SupabaseAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/supabase-auth.php', 'supabase-auth');
        
        $this->registerServices();
        $this->registerContracts();
        $this->validateConfiguration();
    }
    
    private function registerServices(): void
    {
        $this->app->singleton(ConfigurationValidator::class);
        
        $this->app->singleton(CircuitBreakerInterface::class, CircuitBreaker::class);
        $this->app->singleton(RateLimiterInterface::class, RateLimiter::class);
        
        $this->app->singleton(CacheManager::class, function (Container $app): CacheManager {
            return new CacheManager(
                $app->make('cache'),
                $app->make(LoggerInterface::class),
                config('supabase-auth.cache', [])
            );
        });
        
        $this->app->singleton(SupabaseClient::class, function (Container $app): SupabaseClient {
            return new SupabaseClient(
                config('supabase-auth.url'),
                config('supabase-auth.anon_key'),
                config('supabase-auth.service_key'),
                $app->make(LoggerInterface::class),
                $app->make(CircuitBreakerInterface::class),
                $app->make(RateLimiterInterface::class),
                config('supabase-auth.client', [])
            );
        });
        
        $this->app->singleton(SupabaseAuthInterface::class, SupabaseAuth::class);
        $this->app->singleton(SupabaseAuth::class, function (Container $app): SupabaseAuth {
            return new SupabaseAuth(
                $app->make(SupabaseClient::class),
                $app->make(CacheManager::class),
                $app->make(LoggerInterface::class)
            );
        });
    }
    
    private function registerContracts(): void
    {
        $this->app->alias(SupabaseAuth::class, SupabaseAuthInterface::class);
    }
    
    private function validateConfiguration(): void
    {
        if (!$this->app->runningInConsole() && !$this->app->runningUnitTests()) {
            $validator = $this->app->make(ConfigurationValidator::class);
            $validator->validate();
        }
    }
    
    public function boot(): void
    {
        $this->publishResources();
        $this->registerAuthComponents();
        $this->configureAuth();
        $this->loadRoutes();
        $this->registerCommands();
    }
    
    private function publishResources(): void
    {
        $this->publishes([
            __DIR__.'/../config/supabase-auth.php' => config_path('supabase-auth.php'),
        ], 'supabase-auth-config');
        
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'supabase-auth-migrations');
        
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/supabase-auth'),
        ], 'supabase-auth-views');
    }
    
    private function registerAuthComponents(): void
    {
        Auth::provider('supabase', function (Container $app, array $config) {
            return new SupabaseUserProvider(
                $app->make(SupabaseAuthInterface::class),
                $app->make(LoggerInterface::class),
                $config['model']
            );
        });
        
        Auth::extend('supabase', function (Container $app, string $name, array $config) {
            $guard = new SupabaseGuard(
                $name,
                Auth::createUserProvider($config['provider']),
                $app['session.store'],
                $app->make(SupabaseAuthInterface::class),
                $app->make(LoggerInterface::class)
            );
            
            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));
            
            return $guard;
        });
    }
    
    private function loadRoutes(): void
    {
        if (!$this->app->routesAreCached()) {
            $this->loadRoutesFrom(__DIR__.'/routes/auth.php');
        }
    }
    
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Supabase\LaravelAuth\Console\Commands\ValidateConfigCommand::class,
                \Supabase\LaravelAuth\Console\Commands\TestConnectionCommand::class,
                \Supabase\LaravelAuth\Console\Commands\ClearCacheCommand::class,
            ]);
        }
    }
    
    private function configureAuth(): void
    {
        $config = $this->app['config'];
        
        $guards = $config->get('supabase-auth.guards', []);
        foreach ($guards as $name => $guard) {
            $config->set("auth.guards.{$name}", $guard);
        }
        
        $providers = $config->get('supabase-auth.providers', []);
        foreach ($providers as $name => $provider) {
            $config->set("auth.providers.{$name}", $provider);
        }
        
        if ($config->get('supabase-auth.defaults.guard')) {
            $config->set('auth.defaults.guard', $config->get('supabase-auth.defaults.guard'));
        }
    }
}