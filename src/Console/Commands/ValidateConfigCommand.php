<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Console\Commands;

use Illuminate\Console\Command;
use Supabase\LaravelAuth\Services\ConfigurationValidator;

class ValidateConfigCommand extends Command
{
    protected $signature = 'supabase:validate-config
                           {--show-summary : Show configuration summary}
                           {--test-connection : Test connection to Supabase}';
    
    protected $description = 'Validate Supabase authentication configuration';
    
    public function handle(ConfigurationValidator $validator): int
    {
        $this->info('Validating Supabase authentication configuration...');
        
        try {
            $validator->validate();
            $this->info('✅ Configuration validation passed');
            
            if ($this->option('show-summary')) {
                $this->showConfigSummary($validator);
            }
            
            if ($this->option('test-connection')) {
                $this->testConnection($validator);
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Configuration validation failed:');
            $this->error($e->getMessage());
            
            return Command::FAILURE;
        }
    }
    
    private function showConfigSummary(ConfigurationValidator $validator): void
    {
        $this->newLine();
        $this->info('Configuration Summary:');
        $this->line(str_repeat('-', 50));
        
        $summary = $validator->getConfigSummary();
        
        $this->table(
            ['Setting', 'Value'],
            [
                ['Supabase URL', $summary['url']],
                ['Environment', $summary['environment']],
                ['Cache Enabled', $summary['cache_enabled'] ? '✅ Yes' : '❌ No'],
                ['Rate Limiting', $summary['rate_limiting_enabled'] ? '✅ Enabled' : '❌ Disabled'],
                ['Circuit Breaker', $summary['circuit_breaker_enabled'] ? '✅ Enabled' : '❌ Disabled'],
                ['Monitoring', $summary['monitoring_enabled'] ? '✅ Enabled' : '❌ Disabled'],
                ['Secure Cookies', $summary['security']['secure_cookies'] ? '✅ Yes' : '❌ No'],
                ['CSRF Protection', $summary['security']['csrf_protection'] ? '✅ Yes' : '❌ No'],
                ['Password Min Length', $summary['security']['password_min_length']],
            ]
        );
    }
    
    private function testConnection(ConfigurationValidator $validator): void
    {
        $this->newLine();
        $this->info('Testing connection to Supabase...');
        
        $result = $validator->validateConnection();
        
        if ($result['status'] === 'success') {
            $this->info('✅ ' . $result['message']);
        } else {
            $this->error('❌ ' . $result['message']);
        }
        
        $this->line('Timestamp: ' . $result['timestamp']);
    }
}