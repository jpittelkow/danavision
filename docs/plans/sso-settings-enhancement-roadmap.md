# SSO Settings Enhancement Roadmap

Enhance the SSO settings page with proper FormField entries for each provider and add detailed setup instructions via modals or help links.

**Implementation note (2026-01-30):** Phases 1–3, 5–8, and 10 are implemented. Per-provider **enabled** toggles and **per-provider save buttons** are in place. Global options have their own card with a separate save button. Validation hints (Google Client ID, Microsoft UUID, OIDC HTTPS) and user documentation (SSO setup guides) are added. Phase 4 (provider logos) is optional; the SSO page already uses the shared `ProviderIcon` component in card headers.

## Background

The current SSO settings page (`/configuration/sso`) has basic Client ID and Client Secret fields for each provider, but lacks:
- Helpful descriptions/helper text per field
- Setup instructions for configuring OAuth at each provider's developer console
- Links to provider documentation
- Information about required redirect URIs

## Goals

1. **Enhanced FormField entries** - Add proper descriptions, placeholders, and validation hints
2. **Setup instruction modals** - Help users configure OAuth apps at each provider's site
3. **Documentation links** - Direct links to provider developer consoles and docs
4. **Redirect URI display** - Show the callback URL users need to configure
5. **Provider logos** - Visual branding for each SSO provider card
6. **Per-provider save buttons** - Save each provider's settings independently
7. **Test connection buttons** - Verify SSO configuration is working before going live

## Current State

- **Providers supported**: Google, GitHub, Microsoft, Apple, Discord, GitLab, Generic OIDC
- **Fields per provider**: Client ID, Client Secret (OIDC also has Issuer URL, Provider Name)
- **FormField component**: Basic (id, label, error, children) - no description support

## Phase 1: Extend FormField Component

Add description/helper text support to the FormField component.

- [x] Add `description?: string` prop to FormField
- [x] Add `helpLink?: { label: string; url?: string; onClick?: () => void }` prop for help buttons
- [x] Style description as muted text below the label
- [x] Style help link/button inline with label or as info icon

**Files to modify:**
- `frontend/components/ui/form-field.tsx`

## Phase 2: Add SSO Setup Instruction Modal Component

Create a reusable modal component for SSO provider setup instructions.

- [x] Create `SSOSetupModal` component with provider-specific content
- [x] Include step-by-step setup instructions
- [x] Show required redirect URI (computed from `APP_URL` via system settings)
- [x] Include direct link to provider's developer console
- [x] Include link to provider's OAuth documentation

**Files to create/modify:**
- `frontend/components/admin/sso-setup-modal.tsx` (new)
- `frontend/app/(dashboard)/configuration/sso/page.tsx`

## Phase 3: Provider-Specific Setup Instructions

Add detailed setup instructions for each provider.

### Google
- [x] Link: https://console.cloud.google.com/apis/credentials
- [ ] Instructions: Create OAuth 2.0 Client ID, configure consent screen
- [ ] Scopes needed: `email`, `profile`, `openid`
- [ ] Redirect URI: `{APP_URL}/api/auth/callback/google`

### GitHub
- [x] Link: https://github.com/settings/developers
- [ ] Instructions: Create new OAuth App
- [ ] Scopes needed: `user:email`
- [ ] Redirect URI: `{APP_URL}/api/auth/callback/github`

### Microsoft (Azure AD / Entra ID)
- [x] Link: https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps
- [ ] Instructions: Register new application, configure redirect URIs
- [ ] Scopes needed: `openid`, `profile`, `email`
- [ ] Redirect URI: `{APP_URL}/api/auth/callback/microsoft`

### Apple
- [x] Link: https://developer.apple.com/account/resources/identifiers
- [ ] Instructions: Create App ID with Sign in with Apple capability, create Service ID
- [ ] Notes: Requires Apple Developer Program membership, more complex setup
- [ ] Redirect URI: `{APP_URL}/api/auth/callback/apple`

### Discord
- [x] Link: https://discord.com/developers/applications
- [ ] Instructions: Create new application, add OAuth2 redirect
- [ ] Scopes needed: `identify`, `email`
- [ ] Redirect URI: `{APP_URL}/api/auth/callback/discord`

### GitLab
- [x] Link: https://gitlab.com/-/profile/applications (or self-hosted URL)
- [ ] Instructions: Create new application with appropriate scopes
- [ ] Scopes needed: `read_user`, `openid`, `profile`, `email`
- [ ] Redirect URI: `{APP_URL}/api/auth/callback/gitlab`
- [ ] Note: Support self-hosted GitLab instances

### Generic OIDC (Okta, Auth0, Keycloak, etc.)
- [x] General OIDC setup guidance
- [ ] Link to common providers: Okta, Auth0, Keycloak docs
- [ ] Required fields explanation: Issuer URL format, discovery endpoint
- [ ] Redirect URI: `{APP_URL}/api/auth/callback/oidc`

## Phase 4: Provider Logos and Visual Branding (Optional)

Add provider logos to each SSO card for visual clarity.

**See [Branded Iconography Roadmap](branded-iconography-roadmap.md)** for the comprehensive icon implementation plan that covers SSO and other provider icons (notifications, LLM, email, backup).

SSO-specific tasks:
- [x] Display logo in card header alongside provider name
- [x] Ensure SSO settings page uses shared `ProviderIcon` component

**Note:** This phase is optional and can be completed after Branded Iconography is implemented. Core SSO enhancement functionality (Phases 1-3, 5-9) does not require icons.

## Phase 5: Per-Provider Save Buttons

Change from single global save to per-provider save buttons for better UX.

- [x] Add individual save button to each provider card footer
- [x] Track dirty state per provider (not global form)
- [x] Only save the specific provider's settings when its button is clicked
- [x] Show success/error toast per provider
- [x] Disable save button when no changes made to that provider
- [x] Consider keeping global options (Enable SSO, etc.) as separate saveable section

**Benefits:**
- Users can configure one provider at a time without affecting others
- Clearer feedback on what was saved
- Avoids accidental changes to other providers

**Files to modify:**
- `frontend/app/(dashboard)/configuration/sso/page.tsx`
- `backend/app/Http/Controllers/Api/SSOSettingController.php` (may need per-provider endpoint)

## Phase 6: Test Connection Buttons

Add "Test Connection" buttons to verify SSO configuration before going live.

- [x] Add "Test" button to each provider card (next to Save button)
- [x] Create backend endpoint to test SSO provider configuration
- [x] Test should verify:
  - Client ID and Secret are valid
  - Can reach provider's OAuth endpoints
  - Redirect URI is properly configured (if testable)
- [x] Display test results: success, failure with specific error message
- [x] Show loading state during test
- [x] Only enable test button when credentials are filled in

**Backend test implementation:**
- Make a token introspection or discovery request to provider
- For OIDC: Fetch `.well-known/openid-configuration` from issuer URL
- For OAuth providers: Validate credentials format and attempt basic API call
- Return clear error messages (invalid credentials, network error, etc.)

**Files to create/modify:**
- `backend/app/Http/Controllers/Api/SSOSettingController.php` (add test endpoint)
- `backend/routes/api.php` (add test route)
- `frontend/app/(dashboard)/configuration/sso/page.tsx`

## Phase 7: Enhance SSO Settings Page UI

Update the SSO settings page with enhanced FormFields and setup modals.

- [x] Add "Setup instructions" button/link to each provider card header
- [x] Display computed redirect URI in each provider section (read-only, copyable)
- [x] Add field descriptions explaining what each field is for
- [x] Improve placeholders with examples (e.g., "1234567890-abc.apps.googleusercontent.com")
- [x] Add validation hints for common format errors (Google Client ID suffix, Microsoft UUID, OIDC HTTPS)

**Files to modify:**
- `frontend/app/(dashboard)/configuration/sso/page.tsx`

## Phase 8: Fetch APP_URL for Redirect URIs

Ensure the frontend can display the correct redirect URI.

- [x] Create API endpoint or use existing config to expose `APP_URL` to frontend (uses system settings `general.app_url`)
- [x] Display formatted redirect URI per provider
- [x] Add copy-to-clipboard button for redirect URIs

**Files to modify:**
- `backend/app/Http/Controllers/Api/SystemController.php` or config endpoint
- `frontend/app/(dashboard)/configuration/sso/page.tsx`

## Phase 9: Testing & Documentation

- [x] Test setup modal for each provider
- [x] Verify redirect URIs are correctly displayed
- [x] Test copy-to-clipboard functionality
- [x] Update user documentation with SSO setup guides (docs/user/README.md)
- [ ] Add screenshots or flow diagrams if helpful (optional)

**Files to update:**
- `docs/user-docs.md` (SSO configuration section)
- `docs/features.md` (SSO feature description)

## Phase 10: Update Recipes and Patterns

Update the AI development guide so future SSO providers follow the same enhanced format.

- [x] Update `docs/ai/recipes/add-sso-provider.md` to include:
  - Adding `{provider}_enabled` to settings schema and `enabledKey` to providers array
  - Creating setup instructions content for the modal (`sso-setup-modal.tsx`)
  - Adding test connection logic for the new provider type
  - Per-provider save is implemented; each provider card has its own Save button.
- [x] Update `docs/ai/patterns.md` if any new reusable patterns emerge (e.g., test connection pattern)
- [x] Add SSO setup modal content template to recipe for consistency
- [x] Document logo requirements (size, format, light/dark theme compatibility) in add-sso-provider.md Step 7
- [x] Document test connection implementation pattern per provider type

**Files to update:**
- `docs/ai/recipes/add-sso-provider.md`
- `docs/ai/patterns.md` (if applicable)
- `docs/ai/context-loading.md` (add SSO-related files if missing)

## Dependencies

- None (builds on existing SSO settings infrastructure)
- **Optional:** [Branded Iconography](branded-iconography-roadmap.md) for Phase 4 (provider logos)

## Priority

**MEDIUM** - Improves user experience for SSO configuration but not blocking other features

## Estimated Effort

- Phase 1: Small (FormField enhancement)
- Phase 2: Medium (Modal component)
- Phase 3: Medium (Content creation for 7 providers)
- Phase 4: Small (Provider logos)
- Phase 5: Medium (Per-provider save refactor)
- Phase 6: Medium (Test connection backend + frontend)
- Phase 7: Medium (Page UI updates)
- Phase 8: Small (API/config exposure)
- Phase 9: Small (Documentation)
- Phase 10: Small (Recipe and pattern updates)

## Success Criteria

1. Each SSO provider card displays the provider's logo for visual identification
2. Each SSO provider has its own Save button that saves only that provider's settings
3. Each SSO provider has a "Test Connection" button that verifies the configuration works
4. Each SSO provider has a "Setup instructions" button that opens a modal
5. Modal contains step-by-step instructions specific to that provider
6. Modal displays the correct redirect URI to configure
7. Modal includes direct link to provider's developer console
8. FormFields have helpful descriptions and proper placeholders
9. Users can successfully configure and test SSO without leaving the app for documentation
10. The `add-sso-provider` recipe is updated so new providers automatically include all enhanced features (logo, setup modal, test button, per-provider save)

## Implementation Journal

- [SSO Settings Migration (Env to Database Phase 5)](../journal/2026-01-28-sso-settings-migration.md)
- [SSO Settings Enhancement](../journal/2026-01-30-sso-settings-enhancement.md)
- [SSO Redirect URI Fix](../journal/2026-02-05-sso-redirect-uri-fix.md)
- [SSO Callback Page Fix](../journal/2026-02-06-sso-callback-page-fix.md)
- [SSO Session Persistence Fix](../journal/2026-02-06-sso-session-persistence-fix.md)
