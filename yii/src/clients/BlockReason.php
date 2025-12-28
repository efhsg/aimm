<?php

declare(strict_types=1);

namespace app\clients;

/**
 * Reasons for soft blocks detected in page content.
 */
enum BlockReason: string
{
    case None = 'none';
    case Captcha = 'captcha';
    case JavaScriptChallenge = 'javascript_challenge';
    case RateLimitPage = 'rate_limit_page';
    case GeoBlocked = 'geo_blocked';
    case LoginRequired = 'login_required';
    case ServiceUnavailable = 'service_unavailable';
}
