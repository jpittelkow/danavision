# API Key Service Pattern

Manages API key lifecycle: creation, validation, rotation, revocation, and pruning.

## Creating a Key

```php
$result = $apiKeyService->create($user, 'My API Key', $expiresAt);
// Returns: { token: ApiToken, plaintext: string }
// Plaintext format: sk_<64 random chars>
// Only returned once — stored as SHA-256 hash
```

Keys are prefixed with `sk_` and the first 11 characters (`sk_` + 8 random) are stored as `key_prefix` for identification.

## Validating a Key

```php
$token = $apiKeyService->validate($plaintextKey);
// Returns ApiToken or null
// Checks: prefix, hash match, not revoked, not expired, not soft-deleted
// Updates last_used_at on success
```

## Rotation

```php
$result = $apiKeyService->rotate($existingToken);
// Creates new key linked via rotated_from_id
// Old key remains valid for grace period (graphql.key_rotation_grace_days, default 7)
```

## Rate Limiting

`ApiKeyRateLimiter` middleware applies per-key rate limits:

```php
// Per-key limit from token.rate_limit, or system default
$maxAttempts = $token->rate_limit
    ?? $this->settingService->get('graphql', 'default_rate_limit', 60);

// Response headers: X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After (on 429)
```

## Pruning

```php
$count = $apiKeyService->pruneExpired();
// Soft-deletes expired keys
// Revokes + soft-deletes old rotated keys past grace period
```

## Admin Listing

```php
$paginated = $apiKeyService->listAdminKeys([
    'status' => 'active',      // active|expired|revoked
    'user_id' => 5,
    'user' => 'john',          // search name/email
    'expiring_soon' => 'true', // expires within 7 days
], $perPage);
```

## Usage Tracking

```php
$apiKeyService->recordUsage($token, ['query_name' => 'GetUsers']);
// Records via UsageTrackingService with integration='api', provider='graphql'
```

**Key files:** `backend/app/Services/ApiKeyService.php`, `backend/app/Http/Middleware/ApiKeyRateLimiter.php`, `backend/app/Models/ApiToken.php`

**Related:** [Pattern: Security](security.md), [ADR-029](../../adr/029-usage-tracking-alerts.md)
