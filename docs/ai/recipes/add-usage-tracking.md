# Recipe: Add Usage Tracking to an Integration

Record usage events for a new or existing integration type.

## Files to Modify

| File | Action | Purpose |
|------|--------|---------|
| `backend/app/Services/UsageTrackingService.php` | Modify | Add typed recording method |
| `backend/app/Models/IntegrationUsage.php` | Modify | Add integration constant (if new type) |
| Service that generates usage | Modify | Call tracking method |

## Steps

### 1. Add Integration Constant (if new type)

```php
// backend/app/Models/IntegrationUsage.php
const INTEGRATION_MYSERVICE = 'myservice';
const INTEGRATIONS = [..., self::INTEGRATION_MYSERVICE];
```

### 2. Add Typed Recording Method

```php
// backend/app/Services/UsageTrackingService.php
public function recordMyService(string $provider, float $quantity, ?int $userId = null): void
{
    try {
        $this->record(
            IntegrationUsage::INTEGRATION_MYSERVICE,
            $provider,
            'requests',
            $quantity,
            null, // estimated cost, or calculate it
            null, // metadata
            $userId
        );
    } catch (\Exception $e) {
        Log::warning('Failed to record myservice usage', ['error' => $e->getMessage()]);
    }
}
```

### 3. Call from Your Service

```php
// In your service that makes external calls
$this->usageTrackingService->recordMyService('provider-name', 1, $userId);
```

### 4. Add Budget Setting (optional)

Add to `backend/config/settings-schema.php` under the `usage` group:

```php
'budget_myservice' => [
    'type' => 'number',
    'label' => 'Monthly MyService Budget ($)',
    'default' => null,
],
```

Then add the mapping in `UsageAlertService::BUDGET_SETTINGS`:

```php
IntegrationUsage::INTEGRATION_MYSERVICE => 'budget_myservice',
```

## Key Design Rules

- **Always wrap in try/catch** — usage tracking should never break the main flow
- **Use `Log::warning`** on failure, not `Log::error` — it's not critical
- **Include `user_id`** when the action is user-initiated
- **Use `metadata`** for extra context (model name, country, query name, etc.)

## Reference Implementations

- **LLM tracking**: `UsageTrackingService::recordLLM()` — cost estimation, token splitting
- **Email tracking**: `UsageTrackingService::recordEmail()` — simple counter
- **Storage tracking**: `UsageTrackingService::recordStorage()` — bytes uploaded/downloaded
- **API tracking**: `ApiKeyService::recordUsage()` — with query metadata

## Checklist

- [ ] Integration constant added to `IntegrationUsage::INTEGRATIONS`
- [ ] Typed method added to `UsageTrackingService`
- [ ] Wrapped in try/catch with `Log::warning`
- [ ] Called from the service that makes external calls
- [ ] Budget setting added (optional)
- [ ] Budget mapping added to `UsageAlertService` (optional)

**Related:** [ADR-029](../../adr/029-usage-tracking-alerts.md)
