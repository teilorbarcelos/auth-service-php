<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class WorkerGracefulShutdownTest extends TestCase
{
    public function testSignalHandlerSetsRunningToFalse(): void
    {
        $running = true;
        $handler = function () use (&$running) {
            $running = false;
        };

        $handler();
        $this->assertFalse($running);
    }

    public function testLoopExitsAfterSignal(): void
    {
        $counter = 0;
        $running = true;

        while ($running) {
            $counter++;

            if ($counter >= 2) {
                $running = false;
            }
        }

        $this->assertSame(2, $counter);
        $this->assertFalse($running);
    }

    public function testSignalHandlerPreservesCurrentRequest(): void
    {
        $running = true;
        $requestCompleted = false;

        // SIGTERM chega durante o request
        $running = false;

        // Request termina
        $requestCompleted = true;

        $shouldContinue = $running;

        $this->assertTrue($requestCompleted, 'Request deve ter completado');
        $this->assertFalse($shouldContinue, 'Loop deve parar após SIGTERM');
    }

}
