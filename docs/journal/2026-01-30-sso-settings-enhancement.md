# SSO Settings Enhancement - 2026-01-30

## Overview

Implemented the SSO Settings Enhancement roadmap: per-provider **enabled** toggles, **test connection** (credential validation at token endpoint), setup instruction modals with redirect URIs and provider links, and extended FormField (description, helpLink). Login and register pages now show only providers that have credentials, have passed the test, and are enabled.

## Implementation Approach

- **Backend:** Added `{provider}_enabled` and `{provider}_test_passed` to settings schema; ConfigServiceProvider enables a provider only when credentials exist and `_enabled` is true. SSOSettingController enforces enabled/test_passed (clears test_passed when credentials change); test endpoint validates credentials at each provider's token endpoint (invalid_client/401 = fail, invalid_grant/invalid_request = pass) and sets `_test_passed` on success.
- **Frontend:** FormField gained `description` and `helpLink`; SSOSetupModal provides provider-specific setup steps, redirect URI (from app URL), and console/doc links; SSO settings page shows status badges (Not configured → Test required → Test passed/Enabled), Enable toggle only when configured and test passed, Test button when credentials filled, refetch after successful test.
- **Docs:** Updated add-sso-provider recipe, patterns (SSO display, test connection), context-loading, features, roadmap, and API README (SSO Settings Admin section).

## Key Files

- `backend/config/settings-schema.php` (sso: *_enabled, *_test_passed)
- `backend/app/Providers/ConfigServiceProvider.php` (injectSSOConfig)
- `backend/app/Http/Controllers/Api/SSOSettingController.php` (update, test, validateCredentialsAtTokenEndpoint)
- `frontend/components/ui/form-field.tsx` (description, helpLink)
- `frontend/components/admin/sso-setup-modal.tsx`
- `frontend/app/(dashboard)/configuration/sso/page.tsx`
- `docs/ai/recipes/add-sso-provider.md`, `docs/ai/patterns.md`, `docs/api/README.md`

## Observations

- Token-endpoint validation ensures incorrect credentials cannot be marked as "test passed"; Apple is skipped due to JWT client secret complexity.
- Per-provider save (Phase 5) was not implemented; global save remains. Phase 4 (provider logos) and Phase 9 (user docs) left optional.

## Next Steps (Future Considerations)

- Optional: Phase 4 provider logos (see Branded Iconography roadmap); Phase 9 user-docs SSO setup guides; Phase 5 per-provider save if desired.

## Testing Notes

- Verify Test connection enables only when Client ID and Client secret (and Issuer URL for OIDC) are set; success sets test_passed and allows Enable toggle.
- Verify changing credentials clears test_passed for that provider; login page shows only enabled providers with credentials and test passed.
