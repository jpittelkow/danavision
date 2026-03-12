# Security Page Architecture Cleanup - 2026-02-04

## Overview

Separated admin-only authentication configuration from per-user security features and made user security discoverable from the header.

## Implementation Approach

- **User dropdown:** Added a "Security" menu item (Shield icon) linking to `/user/security` so users can find password change, 2FA, passkeys, and SSO management without going through Configuration.
- **Configuration > Security:** Removed duplicated user-level content (password change form, 2FA card, SSO connections, 2FA setup/recovery dialogs). The page now only shows the "Authentication (system-wide)" card (email verification, password reset toggle, 2FA mode, passkey mode). Page description updated to direct users to User menu > Security for personal security settings.
- **2FA required redirect:** API interceptor now redirects to `/user/security` (not `/configuration/security`) when the backend returns `requires_2fa_setup`, so users land where they can set up 2FA.
- **Search:** User security page given a subtitle and extra keywords (mfa, passkeys, sso, connected accounts) in both frontend fallback and backend `search-pages.php`.

## Documentation Updates

- **docs/features.md:** Split "Configurable auth features" into "Admin auth settings (Configuration > Security)" and "User security (User menu > Security)"; added redirect behavior; Configuration Management and User Security sections updated to state how to access each and that per-user features live only at `/user/security`.
- **docs/ai/patterns.md:** API interceptor comment updated to say redirect goes to `/user/security`.
- **docs/plans/configurable-auth-features-roadmap.md:** Frontend file list and api.ts redirect description updated to match the split.
- **docs/adr/018-passkey-webauthn.md:** Passkey registration flow now says "User menu > Security" instead of "Settings > Security".
- **docs/user/README.md:** All "Settings > Profile" and "Settings > Security" references changed to "User menu → My Profile" and "User menu → Security"; Security Settings section intro and step-by-step instructions updated accordingly.

## Observations

- Configuration > Security was previously the only place some users might find 2FA when it was required; moving the redirect to `/user/security` keeps the flow consistent.
- User guide now matches the actual UI (user dropdown, no generic "Settings" section name).

## Next Steps (Future Considerations)

- Consider adding a direct link to Security from the dashboard or profile if analytics show low discovery.
