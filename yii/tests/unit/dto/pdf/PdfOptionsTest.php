<?php

declare(strict_types=1);

namespace tests\unit\dto\pdf;

use app\dto\pdf\PdfOptions;
use Codeception\Test\Unit;

/**
 * @covers \app\dto\pdf\PdfOptions
 */
final class PdfOptionsTest extends Unit
{
    public function testToFormFieldsUsesDefaults(): void
    {
        $options = new PdfOptions();
        $fields = $options->toFormFields();

        $this->assertSame('210mm', $fields['paperWidth']);
        $this->assertSame('297mm', $fields['paperHeight']);
        $this->assertSame('25mm', $fields['marginTop']);
        $this->assertSame('20mm', $fields['marginBottom']);
        $this->assertSame('15mm', $fields['marginLeft']);
        $this->assertSame('15mm', $fields['marginRight']);
        $this->assertSame('1', $fields['scale']);
        $this->assertSame('false', $fields['landscape']);
        $this->assertSame('true', $fields['printBackground']);
        $this->assertSame('false', $fields['preferCssPageSize']);
    }

    public function testLandscapePresetOverridesDefaults(): void
    {
        $options = PdfOptions::landscape();
        $fields = $options->toFormFields();

        $this->assertSame('297mm', $fields['paperWidth']);
        $this->assertSame('210mm', $fields['paperHeight']);
        $this->assertSame('15mm', $fields['marginTop']);
        $this->assertSame('15mm', $fields['marginBottom']);
        $this->assertSame('20mm', $fields['marginLeft']);
        $this->assertSame('20mm', $fields['marginRight']);
        $this->assertSame('true', $fields['landscape']);
    }
}
