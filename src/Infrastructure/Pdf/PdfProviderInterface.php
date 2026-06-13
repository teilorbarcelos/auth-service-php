<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use Psr\Http\Message\StreamInterface;

/**
 * Interface for PDF generation service.
 */
interface PdfProviderInterface
{
    /**
     * Generates a PDF from data and returns a stream for bypass.
     *
     * @param array<string, mixed> $data The PDF request data
     * @return StreamInterface containing the PDF bytes
     */
    public function generatePdf(array $data): StreamInterface;
}
