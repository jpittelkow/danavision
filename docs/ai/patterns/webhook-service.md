# Webhook Service Pattern

Outbound webhooks with HMAC signatures, SSRF protection, and delivery tracking.

## Sending a Webhook

```php
// WebhookService handles SSRF validation, signature, and delivery logging
$result = $this->webhookService->sendTest($webhook);
// Returns: { success: bool, status_code: ?int, message: string, ssrf_blocked?: bool }
```

## HMAC Signature

When a webhook has a `secret`, payloads are signed:

```php
$timestamp = time();
$signaturePayload = $timestamp . '.' . json_encode($payload);
$signature = hash_hmac('sha256', $signaturePayload, $secret);

// Headers sent:
// X-Webhook-Timestamp: 1709568000
// X-Webhook-Signature: sha256=abc123...
```

Consumer verification: recompute the HMAC and compare. Reject if timestamp is older than 5 minutes.

## SSRF Protection

All URLs pass through `UrlValidationService::validateAndResolve()` before HTTP requests. Private/internal IPs are blocked. Use `pinnedOptions()` to prevent DNS rebinding.

```php
$resolved = $this->urlValidator->validateAndResolve($webhook->url);
if ($resolved === null) {
    return ['success' => false, 'ssrf_blocked' => true];
}

Http::withOptions($this->urlValidator->pinnedOptions($resolved))
    ->post($webhook->url, $payload);
```

## Delivery Tracking

Every delivery attempt (success or failure) is recorded in `WebhookDelivery`:

```php
$webhook->deliveries()->create([
    'event' => 'webhook.test',
    'payload' => $payload,
    'response_code' => $response->status(),
    'response_body' => $response->body(),
    'success' => $response->successful(),
]);
```

## Model

```php
// Secret is encrypted at rest, hidden from serialization
protected $hidden = ['secret'];
protected function casts(): array {
    return ['secret' => 'encrypted', 'events' => 'array', 'active' => 'boolean'];
}

// Check if webhook subscribes to an event
$webhook->shouldTrigger('user.created'); // bool
```

**Key files:** `backend/app/Services/WebhookService.php`, `backend/app/Models/Webhook.php`, `backend/app/Http/Controllers/Api/WebhookController.php`

**Related:** [ADR-028](../../adr/028-webhook-system.md), [Pattern: Security](security.md)
