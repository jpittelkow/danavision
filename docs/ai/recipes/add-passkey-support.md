# Recipe: Work with Passkeys (WebAuthn)

Manage user passkeys for passwordless authentication.

## Key Files

| File | Purpose |
|------|---------|
| `backend/app/Services/Auth/PasskeyService.php` | List, rename, delete passkeys |
| `backend/app/Http/Controllers/Api/PasskeyController.php` | API endpoints |
| `frontend/lib/use-passkeys.ts` | Frontend hook for passkey management |
| `backend/app/Models/User.php` | `webauthnCredentials()` relationship |

## PasskeyService API

### List Passkeys

```php
$passkeys = $passkeyService->listPasskeys($user);
// Returns: [{ id, alias, created_at, updated_at }, ...]
```

### Delete a Passkey

```php
$success = $passkeyService->deletePasskey($user, $credentialId);
```

### Rename a Passkey

```php
$success = $passkeyService->renamePasskey($user, $credentialId, 'My Laptop');
```

## Frontend Usage

```typescript
import { usePasskeys } from "@/lib/use-passkeys";
// Hook provides passkey list, register, rename, delete functionality
```

## Registration Flow

Passkey registration uses the WebAuthn browser API. The backend generates a challenge, the browser creates a credential, and the backend verifies and stores it. See [ADR-018](../../adr/018-passkey-webauthn.md) for the full flow.

## Security Notes

- Passkeys are scoped to the user — users can only manage their own
- Credential IDs are opaque identifiers from the WebAuthn spec
- The `alias` field is user-provided display name, not security-critical

**Related:** [ADR-018](../../adr/018-passkey-webauthn.md), [ADR-002](../../adr/002-authentication-architecture.md), [Pattern: Auth Middleware](../patterns/auth-middleware.md)
