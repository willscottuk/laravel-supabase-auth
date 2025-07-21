<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Console\Commands;

use Illuminate\Console\Command;
use Supabase\LaravelAuth\Services\SupabaseClient;
use Supabase\LaravelAuth\Services\SupabaseAuth;
use Supabase\LaravelAuth\Services\CircuitBreaker;

class TestConnectionCommand extends Command
{
    protected $signature = 'supabase:test-connection
                           {--reset-circuit-breaker : Reset circuit breaker before testing}
                           {--detailed : Show detailed connection information}';
    
    protected $description = 'Test connection to Supabase services';
    
    public function handle(
        SupabaseClient $client,
        SupabaseAuth $auth,
        CircuitBreaker $circuitBreaker
    ): int {
        if ($this->option('reset-circuit-breaker')) {
            $circuitBreaker->reset('supabase');
            $this->info('Circuit breaker reset');
        }
        
        $this->info('Testing Supabase connection...');
        
        $tests = [
            'Basic API Connection' => [$this, 'testBasicConnection'],
            'Authentication Service' => [$this, 'testAuthService'],
            'Circuit Breaker Status' => [$this, 'testCircuitBreaker'],
        ];
        
        $results = [];
        $allPassed = true;
        
        foreach ($tests as $name => $test) {
            $this->line("Testing: {$name}");
            
            try {
                $result = $test($client, $auth, $circuitBreaker);
                $results[$name] = $result;
                
                if ($result['status'] === 'success') {
                    $this->info("  ✅ {$result['message']}");
                } else {
                    $this->error("  ❌ {$result['message']}");
                    $allPassed = false;
                }
                
                if ($this->option('detailed') && isset($result['details'])) {
                    foreach ($result['details'] as $detail) {
                        $this->line("    - {$detail}");
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error: {$e->getMessage()}");
                $results[$name] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
                $allPassed = false;
            }
        }
        
        $this->newLine();
        $this->info('Connection Test Summary:');
        $this->line(str_repeat('-', 50));
        
        foreach ($results as $test => $result) {
            $status = $result['status'] === 'success' ? '✅ PASS' : '❌ FAIL';
            $this->line("{$status} - {$test}");
        }
        
        if ($allPassed) {
            $this->newLine();
            $this->info('🎉 All connection tests passed!');
            return Command::SUCCESS;
        } else {
            $this->newLine();
            $this->error('⚠️  Some connection tests failed. Please check your configuration.');
            return Command::FAILURE;
        }
    }
    
    private function testBasicConnection(SupabaseClient $client): array
    {
        try {
            $response = $client->request('GET', '/rest/v1/', [], true);
            
            return [
                'status' => 'success',
                'message' => 'Successfully connected to Supabase API',
                'details' => [
                    'URL: ' . $client->getUrl(),
                    'Response received: ' . (is_array($response) ? 'Array' : gettype($response)),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to connect to Supabase API: ' . $e->getMessage(),
            ];
        }
    }
    
    private function testAuthService(SupabaseClient $client, SupabaseAuth $auth): array
    {
        try {
            // Test JWT verification with a dummy token (will fail but shows service is responsive)
            $result = $auth->verifyToken('dummy.token.test');
            
            return [
                'status' => 'success',
                'message' => 'Authentication service is responsive',
                'details' => [
                    'JWT validation endpoint accessible',
                    'Service properly initialized',
                ],
            ];
        } catch (\Exception $e) {
            // Expected to fail with invalid token, but service should be accessible
            if (str_contains($e->getMessage(), 'token') || str_contains($e->getMessage(), 'JWT')) {
                return [
                    'status' => 'success',
                    'message' => 'Authentication service is responsive',
                    'details' => [
                        'JWT validation working (expected token error)',
                    ],
                ];
            }
            
            return [
                'status' => 'error',
                'message' => 'Authentication service error: ' . $e->getMessage(),
            ];
        }
    }
    
    private function testCircuitBreaker(
        SupabaseClient $client,
        SupabaseAuth $auth,
        CircuitBreaker $circuitBreaker
    ): array {
        $service = 'supabase';
        
        $details = [
            'State: ' . ($circuitBreaker->isClosed($service) ? 'Closed' : 
                        ($circuitBreaker->isOpen($service) ? 'Open' : 'Half-Open')),
            'Failure count: ' . $circuitBreaker->getFailureCount($service),
        ];
        
        if ($circuitBreaker->isOpen($service)) {
            return [
                'status' => 'warning',
                'message' => 'Circuit breaker is OPEN - service calls will be blocked',
                'details' => $details,
            ];
        }
        
        return [
            'status' => 'success',
            'message' => 'Circuit breaker is operational',
            'details' => $details,
        ];
    }
}