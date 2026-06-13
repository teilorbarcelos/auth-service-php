<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Log\RequestIdProcessor;
use App\Infrastructure\Metrics\MetricService;
use App\Modules\Metrics\MetricsController;
use Monolog\LogRecord;
use Monolog\Level;
use Tests\WebTestCase;

class ObservabilityTest extends WebTestCase
{
    public function testRequestIdProcessor(): void
    {
        $processor = new RequestIdProcessor();
        
        // Case 1: requestId is null
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'api',
            Level::Info,
            'test message',
            [],
            []
        );
        $processed = $processor($record);
        $this->assertArrayNotHasKey('request_id', $processed->extra);

        // Case 2: requestId is set
        $processor->setRequestId('test-id');
        $processed = $processor($record);
        $this->assertArrayHasKey('request_id', $processed->extra);
        $this->assertEquals('test-id', $processed->extra['request_id']);
        
        // Case 3: requestId via constructor
        $processorWithId = new RequestIdProcessor('constructor-id');
        $processed = $processorWithId($record);
        $this->assertEquals('constructor-id', $processed->extra['request_id']);
    }

    public function testMetricService(): void
    {
        $metricService = $this->getContainer()->get(MetricService::class);
        $this->assertInstanceOf(MetricService::class, $metricService);
        
        // Test incrementCounter
        $metricService->incrementCounter('test_counter', ['label1'], ['value1']);
        
        // Test recordTimer
        $metricService->recordTimer('test_timer', 123.45, ['label2'], ['value2']);
        
        // Test getRegistry
        $registry = $metricService->getRegistry();
        $this->assertInstanceOf(\Prometheus\CollectorRegistry::class, $registry);
        
        // Verify samples exist
        $samples = $registry->getMetricFamilySamples();
        $foundCounter = false;
        $foundTimer = false;
        
        foreach ($samples as $family) {
            if ($family->getName() === 'app_test_counter') {
                $foundCounter = true;
            }
            if ($family->getName() === 'app_test_timer') {
                $foundTimer = true;
            }
        }
        
        $this->assertTrue($foundCounter, 'Counter not found in samples');
        $this->assertTrue($foundTimer, 'Timer not found in samples');
    }

    public function testMetricsEndpoint(): void
    {
        // Trigger a database query to ensure the query listener is exercised
        try {
            \App\Modules\User\User::first();
        } catch (\Throwable $e) {
            // Fail-safe
        }

        // Trigger an exception to test exceptions_total metric
        $this->request('GET', '/non-existent-route-to-trigger-exception');

        // First trigger a request to ensure some metrics are recorded by LogMiddleware
        $this->request('GET', '/');

        // Now check metrics endpoint
        $response = $this->request('GET', '/metrics');
        
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('app_http_requests_total', $body);
        $this->assertStringContainsString('app_http_request_duration_ms', $body);
        $this->assertStringContainsString('method="GET"', $body);
        $this->assertStringContainsString('path="/"', $body);
        $this->assertStringContainsString('app_database_queries_total', $body);
        $this->assertStringContainsString('app_process_resident_memory_bytes', $body);
        $this->assertStringContainsString('app_process_memory_peak_bytes', $body);
        $this->assertStringContainsString('app_php_gc_runs', $body);
        $this->assertStringContainsString('app_php_gc_collected_total', $body);
        $this->assertStringContainsString('app_php_gc_threshold', $body);
        $this->assertStringContainsString('app_php_gc_roots', $body);
        $this->assertStringContainsString('app_exceptions_total', $body);
    }

    public function testMetricsControllerDirectly(): void
    {
        $metricService = $this->getContainer()->get(MetricService::class);
        $controller = new MetricsController($metricService);
        
        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $response = new \Slim\Psr7\Response();
        
        $response = $controller($request, $response);
        
        $this->assertEquals(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('app_process_resident_memory_bytes', $body);
        $this->assertStringContainsString('app_process_memory_peak_bytes', $body);
        $this->assertStringContainsString('app_php_gc_runs', $body);
    }
}
