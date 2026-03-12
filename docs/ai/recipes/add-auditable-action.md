# Recipe: Add Auditable Action

Step-by-step guide to add a new audited action so it appears in the audit log and complies with naming and security.

## When to Use

- Adding a new feature that changes data or performs a sensitive operation
- Extending an existing controller with an action that should be traceable
- Implementing compliance or security requirements for a new flow

## Steps

### 1. Define the action name

Use the convention `{resource}.{action}`:

- **resource**: The domain (auth, user, settings, backup, email_template, etc.) or a new one (e.g. `api_key`, `webhook`).
- **action**: Verb in past tense or event name (created, updated, deleted, enabled, disabled, revoked, etc.).

Examples:

- `api_key.created`, `api_key.revoked`
- `webhook.updated`, `webhook.deleted`
- `email_template.updated`, `email_template.reset`

### 2. Choose where to log

- **Controller**: Log after the operation succeeds (after `$model->save()`, after `delete()`, etc.).
- **Service**: If the operation is in a service, inject `AuditService` and log from the service, or log from the controller after calling the service.

### 3. Add the logging call

**Generic action (no model):**

```php
$this->auditService->log('resource.action', null, [], ['key' => 'value']);
```

**With a model (auditable):**

```php
$this->auditService->log('resource.updated', $model, $oldValues, $newValues);
```

**Auth-related:**

```php
$this->auditService->logAuth('action_name', $user, ['extra' => 'data'], 'info');
```

**Settings group:**

```php
$this->auditService->logSettings('group_name', $oldValues, $newValues, $request->user()->id);
```

**Model change (convenience):**

```php
$this->auditService->logModelChange($model, 'resource.updated', $oldValues, $newValues);
```

### 4. Include relevant data (old/new values)

- **old_values**: State before the change (e.g. previous name, email, disabled_at). Omit or use `[]` if not applicable.
- **new_values**: State after the change or key context (e.g. filename, group name, user_id).
- **Do not** put passwords, tokens, API keys, or secrets in these arrays; the service masks common keys (password, token, secret, api_key, etc.) but avoid passing raw secrets.

### 5. Set severity when appropriate

- Default is `info`.
- Use `warning` for: failed logins, account disabled, destructive actions (restore, bulk delete).
- Use `error` or `critical` for security or critical system events.

```php
$this->auditService->log('backup.restored', null, [], ['filename' => $name], null, null, 'warning');
```

### 6. Verify in the UI

- Open **Configuration > Audit** in the app.
- Perform the new action and confirm a new row appears with the correct action, severity, and (if applicable) details in the modal.

## Checklist

- [ ] Action name is `{resource}.{action}` and is consistent with existing actions
- [ ] Logging call is after the operation succeeds
- [ ] old_values / new_values do not contain raw secrets
- [ ] Severity is set for destructive or security-relevant actions
- [ ] AuditService is injected (or AuditLogging trait used) in the controller/service
- [ ] New action appears in the audit log UI and export

## Related Documentation

- [AuditService Pattern](../patterns/audit-service.md)
- [Recipe: Trigger audit logging](trigger-audit-logging.md)
- [Audit Logs Implementation Plan](../../plans/audit-logs-implementation-plan.md)

### Implementation Journal

- [Audit Logging Implementation (2026-01-29)](../../journal/2026-01-29-audit-logging-implementation.md)
- [Audit Extended Features (2026-01-29)](../../journal/2026-01-29-audit-extended-features.md)
