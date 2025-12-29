<?php

declare(strict_types=1);

return [
    'schemaPath' => '@app/config/schemas',
    'industriesPath' => '@app/config/industries',
    'datapacksPath' => '@runtime/datapacks',
    'macroStalenessThresholdDays' => 10,
    'renderTimeoutSeconds' => 120,
    'allowedSourceDomains' => [
        'finance.yahoo.com',
        'query1.finance.yahoo.com',
        'www.reuters.com',
        'www.wsj.com',
        'www.bloomberg.com',
        'www.morningstar.com',
        'seekingalpha.com',
        'stockanalysis.com',
    ],

    // Rate limiter: 'file' (dev/single-process) or 'database' (production/multi-process)
    'rateLimiter' => 'file',

    // Alert notifier configuration (set in params-local.php for production)
    'alerts' => [
        'slack_webhook' => null,  // e.g., 'https://hooks.slack.com/services/...'
        'email' => null,          // e.g., 'alerts@example.com'
        'from_email' => 'noreply@aimm.dev',
    ],
];
