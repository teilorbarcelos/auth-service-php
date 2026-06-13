<?php

declare(strict_types=1);

namespace App\Modules\Metrics;

use App\Infrastructure\Metrics\MetricService;
use Prometheus\RenderTextFormat;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MetricsController
{
    public function __construct(private MetricService $metricService) {}

    public function __invoke(Request $request, Response $response): Response
    {
        $token = $_ENV['METRICS_TOKEN'] ?? '';
        if ($token !== '') {
            $authHeader = $request->getHeaderLine('Authorization');
            if (!str_starts_with($authHeader, 'Bearer ') || substr($authHeader, 7) !== $token) {
                return $response
                    ->withStatus(403)
                    ->withHeader('Content-Type', 'application/json');
            }
        }

        // Collect System Metrics
        $registry = $this->metricService->getRegistry();

        // Memory
        $memoryGauge = $registry->getOrRegisterGauge('app', 'process_resident_memory_bytes', 'Memory usage in bytes');
        $memoryGauge->set(memory_get_usage(true));

        $peakMemoryGauge = $registry->getOrRegisterGauge('app', 'process_memory_peak_bytes', 'Peak memory usage in bytes');
        $peakMemoryGauge->set(memory_get_peak_usage(true));

        // GC Metrics
        $gcStatus = gc_status();
        $gcRunsGauge = $registry->getOrRegisterGauge('app', 'php_gc_runs', 'Number of garbage collector runs');
        $gcRunsGauge->set($gcStatus['runs']);

        $gcCollectedGauge = $registry->getOrRegisterGauge('app', 'php_gc_collected_total', 'Number of collected objects');
        $gcCollectedGauge->set($gcStatus['collected']);

        $gcThresholdGauge = $registry->getOrRegisterGauge('app', 'php_gc_threshold', 'Garbage collector threshold');
        $gcThresholdGauge->set($gcStatus['threshold']);

        $gcRootsGauge = $registry->getOrRegisterGauge('app', 'php_gc_roots', 'Garbage collector roots');
        $gcRootsGauge->set($gcStatus['roots']);

        // CPU (Rough estimate using sys_getloadavg or similar if possible, but let's use a simpler approach for now)
        // For a more accurate CPU usage, we'd need to parse /proc/self/stat
        if (file_exists('/proc/self/stat')) {
            $statContent = file_get_contents('/proc/self/stat');
            if ($statContent !== false) {
                $stat = explode(' ', $statContent);
                $utime = (int)($stat[13] ?? 0);
                $stime = (int)($stat[14] ?? 0);
                $cpuGauge = $registry->getOrRegisterGauge('app', 'process_cpu_seconds_total', 'Total user and system CPU time spent in seconds');
                $cpuGauge->set(($utime + $stime) / 100); // Assuming 100Hz clock
            }
        }

        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());

        $response->getBody()->write($result);

        return $response->withHeader('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}
