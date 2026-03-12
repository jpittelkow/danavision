# SSO Settings Migration (Env to Database Phase 5) - 2026-01-28

## Overview

Migrated SSO provider configuration from environment variables to database storage (Phase 5 of the Env to Database roadmap). Admins can configure global SSO options and per-provider OAuth credentials via Configuration > SSO; values fall back to `.env` when not set in the database. A recipe for adding future SSO providers was added.

## Implementation Approach

- **Settings schema** (`backend/config/settings-schema.php`): Added `sso` group with global keys (`enabled`, `allow_linking`, `auto_register`, `trust_provider_email`) and per-provider keys (`google_client_id`, `google_client_secret`, and equivalents for GitHub, Microsoft, Apple, Discord, GitLab, plus OIDC: `oidc_client_id`, `oidc_client_secret`, `oidc_issuer_url`, `oidc_provider_name`). Client secrets marked `encrypted => true`.
- **SSOSettingController**: `show()`, `update()`, `reset($key)`. Validation for booleans and nullable strings; URL validation for `oidc_issuer_url`. Uses SettingService and ApiResponseTrait.
- **ConfigServiceProvider**: Added `injectSSOConfig()` to map DB settings to `config('sso.*')` and `config('services.{provider}.*')` for Laravel Socialite. Provider `enabled` is computed from presence of client_id (and for OIDC, issuer_url).
- **Routes**: `GET/PUT /sso-settings`, `DELETE /sso-settings/keys/{key}` under `can:manage-settings`.
- **Frontend**: New page Configuration > SSO (`/configuration/sso`). Global options card (four switches); one card per provider (Google, GitHub, Microsoft, Apple, Discord, GitLab) with Client ID and Client secret; Enterprise SSO (OIDC) card with client ID, secret, issuer URL, and provider name. Uses react-hook-form with `mode: "onBlur"`, `reset()` for initial values, SaveButton, SettingsPageSkeleton, FormField. SSO link added to configuration layout navigation.
- **Recipe**: Created `docs/ai/recipes/add-sso-provider.md` with steps to add a new OAuth/OIDC provider (schema, ConfigServiceProvider, sso.php, services.php, controller validation, frontend form, env.example).

## Key Files

- `backend/config/settings-schema.php` (sso group)
- `backend/app/Http/Controllers/Api/SSOSettingController.php`
- `backend/app/Providers/ConfigServiceProvider.php` (injectSSOConfig)
- `backend/routes/api.php` (sso-settings routes)
- `frontend/app/(dashboard)/configuration/sso/page.tsx`
- `frontend/app/(dashboard)/configuration/layout.tsx` (SSO nav item)
- `docs/ai/recipes/add-sso-provider.md`

## Challenges Encountered

- **OIDC config**: OIDC is not in `config/services.php` by default; injectSSOConfig sets `config('services.oidc.*')` so runtime config is available for custom Socialite OIDC drivers.
- **Boolean from API**: Backend returns booleans from getGroup(); frontend normalizes with a `toBool()` helper for env/string fallbacks.

## Observations

- Same pattern as LLM and notification settings (schema, controller, ConfigServiceProvider, routes, frontend) kept implementation consistent.
- Provider list is driven by a `providers` array on the frontend so adding a new provider in the recipe is a small, repeatable change.

## Trade-offs

- Redirect URIs are not stored in the schema; they remain env or app-derived (e.g. `APP_URL` + `/api/callback/{provider}`). Documented in the recipe.
- No per-key “reset to default” button in the UI yet; API supports `DELETE /sso-settings/keys/{key}` for future use.

## Next Steps (Future Considerations)

- Phase 6: Migrate backup configuration to database.
- Document which settings remain env-only.
- Optional: Add reset-to-default button per provider or per key on the SSO settings page.

## Testing Notes

- Verify SSO settings load and save via Configuration > SSO.
- Verify provider enabled/disabled state reflects on login page (SSO buttons) when client_id is set or cleared.
- Verify client secrets are stored encrypted in `system_settings`.
- Verify env fallback when DB values are not set (e.g. fresh install or after reset).
