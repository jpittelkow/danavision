# ADR-015: Environment-Only Settings

## Status

Accepted

## Date

2026-01-29

## Context

With database-stored settings and env fallback (ADR-014), most configuration can be managed via the UI. Some settings must remain in `.env` because they are required before the application can connect to the database or use the cache that backs the settings layer.

## Decision

The following settings **must** remain in `.env` only and must not be migrated to the database:

| Category | Variables | Reason |
|----------|-----------|--------|
| Encryption | `APP_KEY` | Required for decryption before DB is available; used by Laravel for cookies, sessions, and encrypted storage |
| Environment | `APP_ENV`, `APP_DEBUG` | Affect error handling and logging before DB and config injection |
| Database | `DB_CONNECTION`, `DB_*` | Database connection itself; needed to read `system_settings` |
| Logging | `LOG_*` | Logging configured before DB; may be needed during bootstrap failures |
| Cache driver | `CACHE_STORE` | Settings service uses file cache; using DB cache would create circular dependency |

All other application settings (mail, notifications, LLM, SSO, backup, etc.) are defined in `backend/config/settings-schema.php` and can be stored in `system_settings` with env fallback.

## Consequences

### Positive

- Clear boundary for what can be managed via UI vs. deployment/config
- Prevents accidental migration of bootstrap-critical settings
- Single reference (this ADR) for “what stays in env”

### Negative

- Changing APP_KEY, DB_*, or CACHE_STORE still requires editing `.env` and restarting

### Neutral

- New migratable settings are added via settings-schema and ConfigServiceProvider; no change to env-only list unless bootstrap requirements change

## Related Decisions

- [ADR-014: Database Settings with Environment Fallback](./014-database-settings-env-fallback.md) – Defines migratable settings and SettingService

## Key Files

- `backend/config/settings-schema.php` – Defines migratable groups (general, mail, notifications, llm, sso, backup)
- `backend/app/Providers/ConfigServiceProvider.php` – Injects DB settings into config; skips when DB not ready
- `.env.example` – Documents all env variables; bootstrap-critical ones have no DB equivalent
