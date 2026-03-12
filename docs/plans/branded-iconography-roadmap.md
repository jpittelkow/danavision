# Branded Iconography Roadmap

Add consistent branded icons/logos for third-party providers across the application.

**Priority**: LOW  
**Status**: Completed (2026-01-29)  
**Dependencies**: None

---

## Overview

Multiple areas of the application reference third-party providers that would benefit from branded iconography for visual clarity and better UX. This roadmap identifies all locations and provides a plan to implement consistent icons.

## Areas Requiring Provider Icons

### 1. SSO Providers (Configuration > SSO) ✅

| Provider | Icon Status | Notes |
|----------|-------------|-------|
| Google | In `provider-icons.tsx` | Google "G" logo |
| GitHub | In `provider-icons.tsx` | Octocat mark |
| Microsoft | In `provider-icons.tsx` | Square logo |
| Apple | In `provider-icons.tsx` | Apple logo |
| Discord | In `provider-icons.tsx` | Clyde logo |
| GitLab | In `provider-icons.tsx` | Tanuki logo |
| OIDC/Enterprise | In `provider-icons.tsx` | Generic key icon |

**Files:**
- `frontend/components/provider-icons.tsx` - Shared icon map (SSO, LLM, backup, etc.)
- `frontend/components/auth/sso-buttons.tsx` - Login/register (uses ProviderIcon)
- `frontend/app/(dashboard)/configuration/sso/page.tsx` - SSO settings (CollapsibleCard + ProviderIcon)

### 2. Notification Channels (Configuration > Notifications, User Preferences)

| Channel | Icon Status | Notes |
|---------|-------------|-------|
| Email | Missing | Envelope icon |
| Telegram | Missing | Telegram logo |
| Discord | Missing | Can reuse from SSO |
| Slack | Missing | Slack logo |
| SMS (Twilio) | Missing | Twilio logo or phone icon |
| SMS (Vonage) | Missing | Vonage logo or phone icon |
| Signal | Missing | Signal logo |
| Web Push | Missing | Bell/notification icon |
| FCM | Missing | Firebase logo |

**Files:**
- `frontend/app/(dashboard)/configuration/notifications/page.tsx` - Admin notification settings
- `frontend/app/(dashboard)/user/preferences/page.tsx` - User notification preferences (if exists)

### 3. LLM Providers (Configuration > LLM System)

| Provider | Icon Status | Notes |
|----------|-------------|-------|
| OpenAI | Missing | OpenAI logo |
| Anthropic (Claude) | Missing | Anthropic logo |
| Google (Gemini) | Missing | Gemini logo or Google AI |
| Ollama | Missing | Ollama logo |
| Azure OpenAI | Missing | Azure logo |
| AWS Bedrock | Missing | AWS logo |

**Files:**
- `frontend/app/(dashboard)/configuration/llm-system/page.tsx` - LLM settings page

### 4. Mail Providers (Configuration > Email)

| Provider | Icon Status | Notes |
|----------|-------------|-------|
| SMTP | Missing | Generic mail server icon |
| Mailgun | Missing | Mailgun logo |
| SendGrid | Missing | SendGrid logo |
| Amazon SES | Missing | AWS/SES logo |
| Postmark | Missing | Postmark logo |

**Files:**
- `frontend/app/(dashboard)/configuration/email/page.tsx` - Email settings page

### 5. Backup Destinations (Configuration > Backup, if exists)

| Destination | Icon Status | Notes |
|-------------|-------------|-------|
| Local | Missing | Folder/disk icon |
| S3 | Missing | AWS S3 logo |
| SFTP | Missing | Server/upload icon |

**Files:**
- TBD - Backup configuration page

---

## Implementation Plan

### Phase 1: Create Shared Icon Infrastructure ✅

- [x] Create `frontend/components/provider-icons.tsx` with all provider icons
- [x] Use inline SVGs for flexibility (no external image files needed)
- [x] Support size prop (sm, md, lg)
- [x] Ensure icons work in both light and dark themes (currentColor / mono)
- [x] Style prop (mono | branded); branded reserved for future official colors
- [x] Export `ProviderIcon` lookup component. See [Patterns: ProviderIcon](../ai/patterns/ui-patterns.md#providericon) and [Recipe: Add provider icon](../ai/recipes/add-provider-icon.md).

### Phase 2: Consolidate Existing Icons ✅

- [x] Extract icons from `sso-buttons.tsx` into shared `provider-icons.tsx`
- [x] GitLab icon in shared component
- [x] Refactor `sso-buttons.tsx` to use `ProviderIcon` from `provider-icons.tsx`
- [x] SSO login buttons use shared icons

### Phase 3: SSO Settings Page ✅

- [x] Add provider icons to SSO settings page (CollapsibleCard headers)
- [x] Display icon alongside provider name in each card

### Phase 4: Notification Settings

- [ ] Add channel icons to notification configuration sections
- [ ] Add channel icons to user preferences page

### Phase 5: LLM Settings

- [ ] Add provider icons to LLM configuration sections
- [ ] Display icon alongside provider name

### Phase 6: Email Settings

- [ ] Add provider icons to mail provider selection/display

### Phase 7: Backup Settings (Future)

- [ ] Add destination icons when backup UI is implemented

---

## Icon Sources & Guidelines

### Brand Logo Guidelines

- Use official SVG logos where available
- Simplify complex logos for small display sizes
- Ensure sufficient contrast in both light and dark modes
- For monochrome contexts, use `currentColor` fill
- For branded contexts, use official brand colors

### Generic Icon Guidelines

For services without distinct branding or for generic categories:
- Use Lucide icons where appropriate (already in project)
- Keep style consistent with existing UI

### Existing Icons in Project

The project already uses Lucide React icons. Check if any needed icons exist there before creating custom SVGs.

---

## Files Created/Modified

**Created:** `frontend/components/provider-icons.tsx` (shared icon component).

**Modified:** `frontend/components/auth/sso-buttons.tsx`, `frontend/app/(dashboard)/configuration/sso/page.tsx`, `frontend/app/(dashboard)/configuration/notifications/page.tsx` (Lucide icons in CollapsibleCard), `frontend/app/(dashboard)/configuration/ai/page.tsx` (ProviderIcon in headers), `frontend/app/(dashboard)/configuration/backup/page.tsx` (ProviderIcon for S3, Google Drive).

---

## Related Roadmaps

- [SSO Settings Enhancement](sso-settings-enhancement-roadmap.md) - Phase 4 references this roadmap for SSO icons
