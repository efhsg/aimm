---
name: enforce-rate-limit
description: Manage request pacing per domain to avoid blocks and respect source policies. Use before every HTTP request in collection. Returns wait decision. Do NOT use for retry logic (handled by collect-datapoint).
---

# EnforceRateLimit

Control request timing to avoid overwhelming sources.

## Interface

```php
interface EnforceRateLimitInterface
{
    public function check(RateLimitRequest $request): RateLimitDecision;
    public function record(string $domain, RequestOutcome $outcome): void;
}
```

## Input

```php
final readonly class RateLimitRequest
{
    public function __construct(
        public string $domain,
        public string $requestIntent,          // fetch|search|api
    ) {}
}
```

## Output

```php
final readonly class RateLimitDecision
{
    public function __construct(
        public bool $proceed,
        public int $waitMs,                    // 0 if proceed=true
        public ?string $reason = null,         // why waiting
    ) {}
}

enum RequestOutcome: string
{
    case Success = 'success';
    case RateLimited = 'rate_limited';         // 429
    case ServerError = 'server_error';         // 5xx
    case Timeout = 'timeout';
}
```

## Algorithm

```
1. GET domain config (minDelay, maxBurst, backoffPolicy)

2. CHECK recent request count
   IF count >= maxBurst in window → wait

3. CHECK last request time
   IF now - lastRequest < minDelay → wait remaining

4. CHECK backoff state
   IF domain in backoff → wait backoff period

5. IF all clear → proceed

record() updates:
- Last request timestamp
- Request count in window
- Backoff state on failures
```

## Domain Configuration

| Domain | Min delay | Max burst/min | Backoff |
|--------|-----------|---------------|---------|
| finance.yahoo.com | 2000ms | 3 | exponential |
| reuters.com | 3000ms | 2 | exponential |
| *.company-ir | 1000ms | 5 | linear |
| eia.gov | 1000ms | 10 | none |
| default | 1000ms | 5 | linear |

## Backoff Rules

**Exponential** (Yahoo, Reuters):
```
attempt 1: 5s
attempt 2: 10s
attempt 3: 20s
attempt 4: 40s
attempt 5: 60s (max)
```

**Linear** (Company IR):
```
attempt N: N * 5s (max 30s)
```

**On 429 response:**
- Use `Retry-After` header if present
- Otherwise 60s wait

## Definition of Done

**Proceed:**
- Within burst limit
- Min delay elapsed
- Not in backoff

**Wait:**
- `waitMs` set to exact delay needed
- `reason` explains why

## Usage

```php
$decision = $rateLimiter->check(new RateLimitRequest(
    domain: 'finance.yahoo.com',
    requestIntent: 'fetch',
));

if (!$decision->proceed) {
    usleep($decision->waitMs * 1000);
}

$response = $client->fetch($url);

$rateLimiter->record(
    'finance.yahoo.com',
    $response->statusCode === 429 
        ? RequestOutcome::RateLimited 
        : RequestOutcome::Success
);
```
