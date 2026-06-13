<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use GuzzleHttp\Client;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

class RemotePdfProvider implements PdfProviderInterface
{
    private Client $client;
    private string $baseUrl;
    private LoggerInterface $logger;

    /** @var array{open: bool, failures: int, last_failure: float, opened_at: float} */
    private array $circuitBreaker = [
        'open' => false,
        'failures' => 0,
        'last_failure' => 0.0,
        'opened_at' => 0.0,
    ];

    private const TIMEOUT = 30.0;
    private const CONNECT_TIMEOUT = 5.0;
    private const FAILURE_THRESHOLD = 3;
    private const RESET_TIMEOUT = 30;
    private const WINDOW = 10;

    public function __construct(LoggerInterface $logger, ?Client $client = null)
    {
        $this->logger = $logger;
        $this->baseUrl = $_ENV['PDF_SERVICE_URL'] ?? 'http://localhost:8889';
        $this->client = $client ?: new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
        ]);
    }

    private function isCircuitOpen(): bool
    {
        if (!$this->circuitBreaker['open']) {
            return false;
        }

        if ((microtime(true) - $this->circuitBreaker['opened_at']) >= self::RESET_TIMEOUT) {
            $this->circuitBreaker['open'] = false;
            $this->circuitBreaker['failures'] = 0;
            return false;
        }

        return true;
    }

    private function recordFailure(): void
    {
        $now = microtime(true);
        if (($now - $this->circuitBreaker['last_failure']) > self::WINDOW) {
            $this->circuitBreaker['failures'] = 0;
        }

        $this->circuitBreaker['failures']++;
        $this->circuitBreaker['last_failure'] = $now;

        if ($this->circuitBreaker['failures'] >= self::FAILURE_THRESHOLD) {
            $this->circuitBreaker['open'] = true;
            $this->circuitBreaker['opened_at'] = $now;
            $this->logger->warning('PDF circuit breaker opened for 30s');
        }
    }

    public function generatePdf(array $data): StreamInterface
    {
        if ($this->isCircuitOpen()) {
            $this->logger->warning('PDF circuit breaker open, using fallback immediately');
            return $this->fallbackPdf();
        }

        $this->logger->info('Generating PDF via remote service', [
            'url' => $this->baseUrl . '/v1/pdf/generate',
            'template' => $data['template'] ?? 'unknown'
        ]);

        try {
            $response = $this->client->post('/v1/pdf/generate', [
                'json' => $data,
                'stream' => true,
            ]);

            return $response->getBody();
        } catch (\Throwable $e) {
            $this->logger->warning('Remote PDF service failed, using fallback: ' . $e->getMessage());
            $this->recordFailure();
            return $this->fallbackPdf();
        }
    }

    private function fallbackPdf(): StreamInterface
    {
        $minimalPdf = "%PDF-1.4\n" .
            "1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n" .
            "2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n" .
            "3 0 obj <</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources <<>> /Contents 4 0 R>> endobj\n" .
            "4 0 obj <</Length 21>> stream\n" .
            "BT /F1 12 Tf 100 700 Td (Fallback PDF Content) Tj ET\n" .
            "endstream\n" .
            "endobj\n" .
            "xref\n" .
            "0 5\n" .
            "0000000000 65535 f\n" .
            "0000000009 00000 n\n" .
            "0000000056 00000 n\n" .
            "0000000111 00000 n\n" .
            "0000000212 00000 n\n" .
            "trailer <</Size 5 /Root 1 0 R>>\n" .
            "startxref\n" .
            "283\n" .
            "%%EOF";

        return \GuzzleHttp\Psr7\Utils::streamFor($minimalPdf);
    }
}
