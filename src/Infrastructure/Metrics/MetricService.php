<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\Redis;

class MetricService
{
    private CollectorRegistry $registry;

    /**
     * @param array<string, mixed> $redisConfig
     * @param Adapter|null $adapter
     */
    public function __construct(array $redisConfig = [], ?Adapter $adapter = null)
    {
        $storage = $adapter ?: new Redis($redisConfig);
        $this->registry = new CollectorRegistry($storage);
    }

    /**
     * Increments a counter.
     *
     * @param string[] $labels
     * @param string[] $labelValues
     */
    public function incrementCounter(string $name, array $labels = [], array $labelValues = []): void
    {
        $counter = $this->registry->getOrRegisterCounter(
            'app',
            $name,
            'Total count of ' . $name,
            $labels
        );
        $counter->inc($labelValues);
    }

    /**
     * Records a duration in a histogram/timer.
     *
     * @param string[] $labels
     * @param string[] $labelValues
     */
    public function recordTimer(string $name, float $durationMs, array $labels = [], array $labelValues = []): void
    {
        $histogram = $this->registry->getOrRegisterHistogram(
            'app',
            $name,
            'Duration of ' . $name . ' in ms',
            $labels,
            [10, 50, 100, 200, 500, 1000, 2000, 5000] // Default buckets
        );
        $histogram->observe($durationMs, $labelValues);
    }

    public function getRegistry(): CollectorRegistry
    {
        return $this->registry;
    }
}
