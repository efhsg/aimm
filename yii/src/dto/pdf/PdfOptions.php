<?php

declare(strict_types=1);

namespace app\dto\pdf;

/**
 * PdfOptions defines the visual configuration for PDF generation.
 *
 * It maps to Gotenberg's Chromium conversion parameters like paper size, margins, and scale.
 */
final readonly class PdfOptions
{
    public function __construct(
        public string $paperWidth = '210mm',
        public string $paperHeight = '297mm',
        public string $marginTop = '25mm',
        public string $marginBottom = '20mm',
        public string $marginLeft = '15mm',
        public string $marginRight = '15mm',
        public float $scale = 1.0,
        public bool $landscape = false,
        public bool $printBackground = true,
    ) {
    }

    /** @return array<string, string> */
    public function toFormFields(): array
    {
        return [
            'paperWidth' => $this->paperWidth,
            'paperHeight' => $this->paperHeight,
            'marginTop' => $this->marginTop,
            'marginBottom' => $this->marginBottom,
            'marginLeft' => $this->marginLeft,
            'marginRight' => $this->marginRight,
            'scale' => (string) $this->scale,
            'landscape' => $this->landscape ? 'true' : 'false',
            'printBackground' => $this->printBackground ? 'true' : 'false',
            'preferCssPageSize' => 'false',
        ];
    }

    public static function standard(): self
    {
        return new self();
    }

    public static function landscape(): self
    {
        return new self(
            paperWidth: '297mm',
            paperHeight: '210mm',
            marginTop: '15mm',
            marginBottom: '15mm',
            marginLeft: '20mm',
            marginRight: '20mm',
            landscape: true,
        );
    }
}
