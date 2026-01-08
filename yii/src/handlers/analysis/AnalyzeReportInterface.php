<?php

declare(strict_types=1);

namespace app\handlers\analysis;

use app\dto\analysis\AnalyzeReportRequest;
use app\dto\analysis\AnalyzeReportResult;

interface AnalyzeReportInterface
{
    public function handle(AnalyzeReportRequest $request): AnalyzeReportResult;
}
