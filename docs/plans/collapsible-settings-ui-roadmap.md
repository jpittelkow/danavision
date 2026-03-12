# Collapsible Settings UI Roadmap

Implement collapsible sections in configuration, settings, and preferences pages to organize provider/setting groups and save screen space.

## Background

Currently, settings pages like LLM Providers, Notifications, and SSO display all providers/sections expanded at once. As more providers are added, these pages become long and harder to navigate. Collapsible sections would:

- Reduce visual clutter
- Allow users to focus on one provider at a time
- Make pages more manageable on smaller screens
- Provide a consistent pattern across all configuration pages

## Goals

1. **Collapsible provider sections** - Each provider card/section can expand/collapse
2. **Consistent UX** - Same collapsible pattern across all config pages
3. **Persistence** - Remember which sections are expanded (optional)
4. **Accessibility** - Proper keyboard navigation and ARIA attributes
5. **Mobile-friendly** - Works well on responsive layouts

## Affected Pages

| Page | Section Type | Current State |
|------|--------------|---------------|
| `/configuration/llm-system` | LLM Providers (OpenAI, Anthropic, Google, etc.) | All expanded |
| `/configuration/notifications` | Notification Channels (Email, Slack, Discord, etc.) | All expanded |
| `/configuration/sso` | SSO Providers (Google, GitHub, Microsoft, etc.) | All expanded |
| `/configuration/backup` | Backup Providers (Local, S3, etc.) | All expanded |
| `/configuration/email` | SMTP settings | Single section |
| `/user/profile` | User preferences sections | Mixed |

## Phase 1: Create Collapsible Component ✅

Create a reusable collapsible/accordion component using shadcn/ui.

- [x] Evaluate using shadcn/ui `Accordion` or `Collapsible` component (used Radix Collapsible)
- [x] Create wrapper component if needed for consistent styling (`CollapsibleCard`)
- [x] Support single-expand vs multi-expand modes (multi-expand via multiple cards)
- [x] Add smooth expand/collapse animations (CSS grid-rows transition)
- [x] Include expand/collapse icon indicator (chevron)
- [x] Support custom header content (icon, title, status badge, headerActions)

**Files:** `frontend/components/ui/collapsible-card.tsx`. See [Patterns: CollapsibleCard](../ai/patterns/ui-patterns.md#collapsiblecard) and [Recipe: Add collapsible section](../ai/recipes/add-collapsible-section.md).

## Phase 2: LLM Providers Page ✅

Apply collapsible sections to the LLM System settings page.

- [x] Wrap each LLM provider (OpenAI, Anthropic, Google, Ollama, etc.) in collapsible
- [x] Show provider name and enabled/disabled status in collapsed header
- [x] Collapse all by default, expand on click (primary provider default open)
- [x] Keep provider icon visible in collapsed state
- [x] Ensure save functionality works for collapsed sections

**Files:** `frontend/app/(dashboard)/configuration/ai/page.tsx` (AI/LLM config).

## Phase 3: Notification Settings Page ✅

Apply collapsible sections to the Notifications configuration page.

- [x] Wrap each notification channel (credential section) in collapsible
- [x] Show channel name and configured status in header
- [x] Include channel icon in collapsed state
- [x] Group related settings logically within each collapsible

**Files:** `frontend/app/(dashboard)/configuration/notifications/page.tsx`

## Phase 4: SSO Providers Page ✅

Apply collapsible sections to the SSO configuration page.

- [x] Wrap each SSO provider in collapsible
- [x] Show provider name, logo, and configured status in header
- [x] "Configured" vs "Not configured" badge in header

**Files:** `frontend/app/(dashboard)/configuration/sso/page.tsx`

## Phase 5: Backup Settings Page ✅

Apply collapsible sections to the Backup configuration page.

- [x] Wrap each backup destination (S3, SFTP, Google Drive) in collapsible
- [x] Show provider name and status in header

**Files:** `frontend/app/(dashboard)/configuration/backup/page.tsx`

## Phase 6: User Preferences Page

Review and apply collapsible sections to user profile/preferences if beneficial.

- [ ] Evaluate which sections benefit from collapsible UI
- [ ] Apply consistently with other pages

**Files to modify:**
- `frontend/app/(dashboard)/user/profile/page.tsx`

## Phase 7: State Persistence (Optional)

Remember which sections users have expanded.

- [ ] Store expanded state in localStorage per page
- [ ] Restore state on page load
- [ ] Consider per-user database storage for cross-device sync (future)

## Phase 8: Testing & Polish

- [ ] Test keyboard navigation (Enter/Space to toggle)
- [ ] Verify ARIA attributes for accessibility
- [ ] Test on mobile viewports
- [ ] Ensure forms within collapsed sections validate correctly
- [ ] Test that save actions work regardless of collapsed state

## Phase 9: Documentation ✅

- [x] Update `docs/ai/patterns.md` with CollapsibleCard pattern
- [x] Add [Recipe: Add collapsible section](../ai/recipes/add-collapsible-section.md)
- [x] Document component usage in code comments and docs

**Files:** `docs/ai/patterns.md`, `docs/ai/recipes/add-collapsible-section.md`, `docs/features.md`, `docs/ai/context-loading.md`

## Dependencies

- None (can start immediately)

## Priority

**LOW** - UI enhancement for better organization, not blocking other features

## Success Criteria

1. All multi-provider configuration pages use collapsible sections
2. Collapsible behavior is consistent across all pages
3. Collapsed headers show enough info to identify the section (name, icon, status)
4. Sections can be expanded/collapsed without affecting save functionality
5. Accessible via keyboard (Tab, Enter/Space)
6. Smooth animations on expand/collapse
7. Works well on mobile devices
