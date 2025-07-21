<?php

declare(strict_types=1);

namespace Supabase\LaravelAuth\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Supabase\LaravelAuth\Contracts\CircuitBreakerInterface;
use Supabase\LaravelAuth\Contracts\RateLimiterInterface;

class SupabaseClient
{
    private Client $httpClient;
    private string $url;
    private string $anonKey;
    private string $serviceKey;
    private LoggerInterface $logger;
    private CircuitBreakerInterface $circuitBreaker;
    private RateLimiterInterface $rateLimiter;
    private array $config;
    
    public function __construct(
        string $url,
        string $anonKey,
        string $serviceKey,
        LoggerInterface $logger,
        CircuitBreakerInterface $circuitBreaker,
        RateLimiterInterface $rateLimiter,
        array $config = []
    ) {
        $this->url = rtrim($url, '/');
        $this->anonKey = $anonKey;
        $this->serviceKey = $serviceKey;
        $this->logger = $logger;
        $this->circuitBreaker = $circuitBreaker;
        $this->rateLimiter = $rateLimiter;
        $this->config = $config;
        
        $this->httpClient = new Client([
            'base_uri' => $this->url,
            'timeout' => $config['timeout'] ?? 10.0,
            'connect_timeout' => $config['connect_timeout'] ?? 5.0,
            'verify' => $config['verify_ssl'] ?? true,
            'headers' => [
                'User-Agent' => $config['user_agent'] ?? 'Supabase-Laravel-Auth/1.0',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }
    
    public function request(string $method, string $endpoint, array $options = [], bool $useServiceKey = false): array
    {
        $requestId = $this->generateRequestId();
        $startTime = microtime(true);
        
        try {
            // Apply rate limiting
            $this->rateLimiter->attempt(
                $this->getRateLimitKey($method, $endpoint),
                $this->config['rate_limit_requests'] ?? 100,
                1
            );
            
            // Use circuit breaker
            return $this->circuitBreaker->call(function () use ($method, $endpoint, $options, $useServiceKey, $requestId) {
                return $this->executeRequest($method, $endpoint, $options, $useServiceKey, $requestId);
            });
            
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logRequest($method, $endpoint, $requestId, $duration, false, $e->getMessage());
            throw $e;
        }
    }
    
    private function executeRequest(string $method, string $endpoint, array $options, bool $useServiceKey, string $requestId): array
    {
        $startTime = microtime(true);
        $headers = $options['headers'] ?? [];
        
        // Set authentication headers
        if ($useServiceKey) {
            $headers['apikey'] = $this->serviceKey;
            $headers['Authorization'] = 'Bearer ' . $this->serviceKey;
        } else {
            $headers['apikey'] = $this->anonKey;
        }
        
        $headers['X-Request-ID'] = $requestId;
        $options['headers'] = $headers;
        
        // Add retry logic
        $maxAttempts = $this->config['retry_attempts'] ?? 3;
        $retryDelay = $this->config['retry_delay'] ?? 1000;
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->httpClient->request($method, $endpoint, $options);
                $responseBody = $response->getBody()->getContents();
                $data = json_decode($responseBody, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
                }
                
                $duration = microtime(true) - $startTime;
                $this->logRequest($method, $endpoint, $requestId, $duration, true);
                
                return $data ?? [];
                
            } catch (RequestException $e) {
                $isLastAttempt = $attempt === $maxAttempts;
                
                if (!$isLastAttempt && $this->shouldRetry($e)) {
                    usleep($retryDelay * 1000 * $attempt); // Progressive delay
                    continue;
                }
                
                $this->handleRequestException($e, $method, $endpoint, $requestId);
            }
        }
        
        throw new \Exception('Max retry attempts exceeded');
    }
    
    private function shouldRetry(RequestException $e): bool
    {
        $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
        
        // Retry on 5xx errors and specific 4xx errors
        return $statusCode >= 500 || in_array($statusCode, [408, 409, 429]);
    }
    
    private function handleRequestException(RequestException $e, string $method, string $endpoint, string $requestId): void
    {
        $response = $e->getResponse();
        $statusCode = $response ? $response->getStatusCode() : 0;
        $responseBody = null;
        
        if ($response) {
            try {
                $responseBody = json_decode($response->getBody()->getContents(), true);
            } catch (\Exception $jsonException) {
                $this->logger->warning('Failed to parse error response JSON', [
                    'request_id' => $requestId,
                    'exception' => $jsonException->getMessage()
                ]);
            }
        }
        
        $errorMessage = $responseBody['error_description'] 
            ?? $responseBody['message'] 
            ?? $responseBody['error'] 
            ?? $e->getMessage();
        
        $this->logger->error('Supabase API request failed', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'error' => $errorMessage,
            'request_id' => $requestId,
        ]);
        
        throw new \Exception($errorMessage, $statusCode);
    }
    
    private function generateRequestId(): string
    {
        return uniqid('sup_', true);
    }
    
    private function getRateLimitKey(string $method, string $endpoint): string
    {
        return 'supabase_client:' . strtolower($method) . ':' . hash('sha256', $endpoint);
    }
    
    private function logRequest(string $method, string $endpoint, string $requestId, float $duration, bool $success, ?string $error = null): void
    {
        $context = [
            'method' => $method,
            'endpoint' => $endpoint,
            'duration_ms' => round($duration * 1000, 2),
            'request_id' => $requestId,
            'success' => $success,
        ];
        
        if ($error) {
            $context['error'] = $error;
        }
        
        $this->logger->info('Supabase API request completed', $context);
    }
    
    public function getUrl(): string
    {
        return $this->url;
    }
    
    public function getAnonKey(): string
    {
        return $this->anonKey;
    }
    
    public function getServiceKey(): string
    {
        return $this->serviceKey;
    }
    
    public function healthCheck(): array
    {
        try {
            $startTime = microtime(true);
            $response = $this->request('GET', '/rest/v1/', [], true);
            $duration = microtime(true) - $startTime;
            
            return [
                'status' => 'healthy',
                'response_time_ms' => round($duration * 1000, 2),
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ];
        }
    }
}