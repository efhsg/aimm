<?php

declare(strict_types=1);

return [
    'schemaPath' => '@app/config/schemas',
    'industriesPath' => '@app/config/industries',
    'datapacksPath' => '@runtime/datapacks',
    'macroStalenessThresholdDays' => 10,
    'renderTimeoutSeconds' => 120,
    'allowedSourceDomains' => [
        'financialmodelingprep.com',
        'finance.yahoo.com',
        'query1.finance.yahoo.com',
        'www.reuters.com',
        'www.wsj.com',
        'www.bloomberg.com',
        'www.morningstar.com',
        'seekingalpha.com',
        'stockanalysis.com',
        'rigcount.bakerhughes.com',
        'api.eia.gov',
        'www.ecb.europa.eu',
    ],
    // Baker Hughes rotates this XLSX URL; override in params-local.php when it changes.
    'rigCountXlsxUrl' => 'https://rigcount.bakerhughes.com/static-files/ec8abd8d-2b0f-4977-bb6f-8b9814fc8401',
    'eiaApiKey' => 'DEMO_KEY',
    'eiaInventorySeriesId' => 'PET.WCRSTUS1.W',

    // Financial Modeling Prep API key
    'fmpApiKey' => getenv('FMP_API_KEY') ?: null,

    // Rate limiter: 'file' (dev/single-process) or 'database' (production/multi-process)
    'rateLimiter' => 'file',

    // Alert notifier configuration (set in params-local.php for production)
    'alerts' => [
        'slack_webhook' => null,  // e.g., 'https://hooks.slack.com/services/...'
        'email' => null,          // e.g., 'alerts@example.com'
        'from_email' => 'noreply@aimm.dev',
    ],
];
