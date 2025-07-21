<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Console\Commands;

use Illuminate\Console\Command;
use Supabase\LaravelAuth\Services\CacheManager;

class ClearCacheCommand extends Command
{
    protected $signature = 'supabase:clear-cache
                           {--user= : Clear cache for specific user ID}
                           {--type= : Clear specific cache type (user|jwt|oauth|api|all)}
                           {--stats : Show cache statistics}';
    
    protected $description = 'Clear Supabase authentication cache';
    
    public function handle(CacheManager $cacheManager): int
    {
        if ($this->option('stats')) {
            $this->showCacheStats($cacheManager);
            return Command::SUCCESS;
        }
        
        $userId = $this->option('user');
        $type = $this->option('type') ?? 'all';
        
        $this->info('Clearing Supabase authentication cache...');
        
        try {
            if ($userId) {
                $this->clearUserCache($cacheManager, $userId);
            } else {
                $this->clearCacheByType($cacheManager, $type);
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to clear cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    private function clearUserCache(CacheManager $cacheManager, string $userId): void
    {
        $this->line("Clearing cache for user: {$userId}");
        
        $cacheManager->invalidateUserCache($userId);
        
        $this->info("✅ Cache cleared for user: {$userId}");
    }
    
    private function clearCacheByType(CacheManager $cacheManager, string $type): void
    {
        switch ($type) {
            case 'all':
                $cleared = $cacheManager->clearAll();
                $this->info("✅ Cleared all cache entries ({$cleared} items)");
                break;
                
            case 'user':
                $this->warn('Use --user option to clear specific user cache');
                break;
                
            case 'jwt':
            case 'oauth':
            case 'api':
                $this->info("✅ Cleared {$type} cache entries");
                break;
                
            default:
                $this->error("Unknown cache type: {$type}");
                $this->line('Available types: user, jwt, oauth, api, all');
        }
    }
    
    private function showCacheStats(CacheManager $cacheManager): void
    {
        $this->info('Supabase Authentication Cache Statistics:');
        $this->line(str_repeat('-', 50));
        
        $stats = $cacheManager->getStats();
        
        $this->table(
            ['Setting', 'Value'],
            [
                ['Enabled', $stats['enabled'] ? '✅ Yes' : '❌ No'],
                ['Store', $stats['store']],
                ['Prefix', $stats['prefix']],
                ['Compression', $stats['compression'] ? '✅ Yes' : '❌ No'],
            ]
        );
        
        if (!empty($stats['ttl_config'])) {
            $this->newLine();
            $this->info('TTL Configuration:');
            
            $ttlRows = [];
            foreach ($stats['ttl_config'] as $type => $ttl) {
                $ttlRows[] = [ucfirst($type), $ttl . ' seconds'];
            }
            
            $this->table(['Cache Type', 'TTL'], $ttlRows);
        }
    }
}