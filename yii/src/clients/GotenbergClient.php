<?php

declare(strict_types=1);

namespace app\clients;

use app\dto\pdf\PdfOptions;
use app\dto\pdf\RenderBundle;
use app\exceptions\GotenbergException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use Yii;

/**
 * GotenbergClient interacts with the Gotenberg API to render PDF documents from HTML.
 *
 * It supports multi-part requests with index.html, header.html, footer.html, and additional assets.
 */
class GotenbergClient
{
    private const ENDPOINT = '/forms/chromium/convert/html';
    private const CONNECT_TIMEOUT = 2.0;
    private const TIMEOUT = 30.0;

    public function __construct(
        private readonly Client $httpClient,
        private readonly string $baseUrl = 'http://aimm_gotenberg:3000',
    ) {
    }

    /**
     * @throws GotenbergException
     */
    public function render(RenderBundle $bundle, PdfOptions $options): string
    {
        $multipart = $this->buildMultipart($bundle, $options);

        try {
            $response = $this->httpClient->post($this->baseUrl . self::ENDPOINT, [
                'headers' => [
                    'X-Trace-Id' => $bundle->traceId,
                ],
                'body' => new MultipartStream($multipart),
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'timeout' => self::TIMEOUT,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($status >= 400) {
                $snippet = substr($body, 0, 2000);
                $retryable = $status >= 500;

                Yii::error([
                    'message' => 'Gotenberg render failed',
                    'traceId' => $bundle->traceId,
                    'status' => $status,
                    'body' => $snippet,
                ], self::class);

                throw new GotenbergException(
                    "Failed to render PDF (HTTP {$status})",
                    $status,
                    null,
                    retryable: $retryable,
                    statusCode: $status,
                    responseBodySnippet: $snippet,
                );
            }

            return $body;
        } catch (GuzzleException $e) {
            Yii::error([
                'message' => 'Gotenberg render failed',
                'traceId' => $bundle->traceId,
                'error' => $e->getMessage(),
            ], self::class);

            throw new GotenbergException(
                "Failed to render PDF: {$e->getMessage()}",
                $e->getCode(),
                $e,
                retryable: true,
            );
        }
    }

    /**
     * @return array<int, array{name: string, contents: string|resource, filename?: string}>
     */
    private function buildMultipart(RenderBundle $bundle, PdfOptions $options): array
    {
        $parts = [];

        foreach ($options->toFormFields() as $name => $value) {
            $parts[] = ['name' => $name, 'contents' => $value];
        }

        $parts[] = [
            'name' => 'files',
            'contents' => $bundle->indexHtml,
            'filename' => 'index.html',
        ];

        if ($bundle->headerHtml !== null) {
            $parts[] = [
                'name' => 'files',
                'contents' => $bundle->headerHtml,
                'filename' => 'header.html',
            ];
        }

        if ($bundle->footerHtml !== null) {
            $parts[] = [
                'name' => 'files',
                'contents' => $bundle->footerHtml,
                'filename' => 'footer.html',
            ];
        }

        foreach ($bundle->files as $path => $content) {
            $parts[] = [
                'name' => 'files',
                'contents' => $content,
                'filename' => $path,
            ];
        }

        return $parts;
    }
}
