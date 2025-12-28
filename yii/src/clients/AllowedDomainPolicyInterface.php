<?php

declare(strict_types=1);

namespace app\clients;

/**
 * Domain policy for SSRF + redirect safety.
 */
interface AllowedDomainPolicyInterface
{
    /**
     * @throws \app\exceptions\NetworkException when URL is not allowed (scheme/host/IP/redirect policy)
     */
    public function assertAllowed(string $url): void;
}
