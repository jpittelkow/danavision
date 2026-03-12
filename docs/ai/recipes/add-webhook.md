# Recipe: Add a New Webhook Event

Register a new event type that triggers outbound webhooks.

## Files to Modify

| File | Action | Purpose |
|------|--------|---------|
| Service that generates the event | Modify | Trigger webhook delivery |
| `backend/app/Services/WebhookService.php` | May modify | If adding delivery logic beyond test |

## Steps

### 1. Define the Event Name

Use `{resource}.{action}` naming convention (same as audit actions):

```
user.created, user.updated, user.deleted
backup.completed, backup.failed
payment.received, payment.refunded
```

### 2. Trigger Webhooks from Your Service

```php
use App\Models\Webhook;
use App\Services\WebhookService;

// Find webhooks subscribed to this event
$webhooks = Webhook::where('active', true)->get()
    ->filter(fn ($w) => $w->shouldTrigger('user.created'));

foreach ($webhooks as $webhook) {
    // Build your payload
    $payload = [
        'event' => 'user.created',
        'timestamp' => now()->toIso8601String(),
        'data' => [
            'user_id' => $user->id,
            'email' => $user->email,
        ],
    ];

    // Use WebhookService for SSRF-safe delivery with signatures
    app(WebhookService::class)->deliver($webhook, 'user.created', $payload);
}
```

### 3. Document Available Events

Add the event to the webhook configuration UI so users can subscribe to it. Update the events list in the frontend webhook form.

## Security Checklist

- [ ] Payload does not include secrets, passwords, or tokens
- [ ] Event name follows `resource.action` convention
- [ ] Webhook URL validated via `UrlValidationService` (handled by `WebhookService`)
- [ ] Delivery logged to `WebhookDelivery` for debugging

## Reference Implementation

- **Test delivery**: `WebhookService::sendTest()` — includes SSRF check, HMAC signature, delivery logging
- **Webhook model**: `Webhook::shouldTrigger($event)` — checks active + event subscription

**Related:** [ADR-028](../../adr/028-webhook-system.md), [Pattern: Webhook Service](../patterns/webhook-service.md), [Pattern: Security](../patterns/security.md)
