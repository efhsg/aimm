<?php

declare(strict_types=1);

namespace app\commands;

use app\clients\GotenbergClient;
use app\dto\pdf\PdfOptions;
use app\dto\pdf\RenderBundle;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * PdfController provides console commands for managing and testing PDF generation.
 */
final class PdfController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly GotenbergClient $gotenbergClient,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionTest(): int
    {
        $traceId = sprintf('test-%s', date('YmdHis'));
        $generatedAt = date('Y-m-d H:i:s');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Test PDF</title>
    <link rel="stylesheet" href="assets/test.css">
</head>
<body>
    <h1>Hello from Gotenberg!</h1>
    <p>Generated at: {$generatedAt}</p>
</body>
</html>
HTML;

        $css = <<<'CSS'
body { font-family: sans-serif; padding: 20mm; }
h1 { color: #333; }
CSS;

        $bundle = RenderBundle::factory($traceId)
            ->withIndexHtml($html)
            ->addFile('assets/test.css', $css, strlen($css))
            ->build();

        $this->stdout("Generating test PDF with traceId: {$traceId}\n");

        try {
            $pdfBytes = $this->gotenbergClient->render($bundle, PdfOptions::standard());

            $outputPath = Yii::getAlias('@runtime') . "/test-{$traceId}.pdf";
            file_put_contents($outputPath, $pdfBytes);

            $this->stdout("PDF generated: {$outputPath}\n");
            $this->stdout('Size: ' . strlen($pdfBytes) . " bytes\n");

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("Error: {$e->getMessage()}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
