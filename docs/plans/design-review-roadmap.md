# Design Review Roadmap

Comprehensive design audit and visual polish across the application — login, dashboard, sidebar, header, notification system, user section, admin configuration, and help center. Reviewed with a focus on **mobile/PWA readiness**, **shadcn/ui block alignment**, and **touch accessibility**.

**Priority**: MEDIUM
**Status**: COMPLETED (all Phase 1-4 items complete)
**Last Updated**: 2026-03-08
**Re-reviewed**: 2026-03-08 (mobile/PWA + shadcn blocks audit)

---

## Overview

The app has a solid functional foundation built on shadcn/ui with strong mobile-first patterns already in place: `useIsMobile()` hook at 768px, Sheet-based mobile drawers, `min-h-svh` viewport handling, PWA standalone mode with service worker caching, push notifications, background sync, and install prompts. The `tailwindcss-animate` plugin is available but no motion library (framer-motion) is installed.

This roadmap addresses targeted design improvements organized by priority, with each item assessed for mobile/PWA impact and alignment with shadcn/ui blocks and component patterns.

### shadcn/ui Block Reference

These official shadcn blocks define the visual patterns we should align with:

| Block | Pattern | Relevance |
|-------|---------|-----------|
| **login-02** | Split-panel with cover image (hidden on mobile) | Login page redesign |
| **login-03** | Centered card on muted background | Current login pattern (baseline) |
| **sidebar-07** | Collapsible sidebar → icon-only | Main sidebar pattern |
| **sidebar-05** | Collapsible submenus with search | Config nav pattern |
| **dashboard-01** | Sidebar + charts + data table + section cards | Dashboard composition |

---

## Phase 1: Critical — Component Consistency & Mobile Fixes

Items that fix broken patterns, accessibility gaps, or shadcn component misuse. These should be addressed first as they affect correctness and consistency.

### 1. Safe Area & Viewport Gaps (NEW)

PWA standalone mode on iOS/Android needs safe area insets for notch and home indicator. The CSS utilities exist in `globals.css` but are **not applied** to layout components.

**Files**: `frontend/app/layout.tsx`, `frontend/components/header.tsx`, `frontend/components/sidebar.tsx`

- [x] **Apply `safe-area-top` to header** — The sticky header at `top-0` will overlap the iOS notch in standalone mode; apply `env(safe-area-inset-top)` padding
- [x] **Apply `safe-area-bottom` to relevant containers** — Bottom-positioned elements (mobile sheet actions, form submit buttons) need `env(safe-area-inset-bottom)` padding
- [x] **Verify viewport meta includes `viewport-fit=cover`** — Required for safe area insets to work; Next.js may set this automatically but confirm in the rendered HTML

> **PWA Impact**: HIGH — Without this, the app header is partially hidden behind the notch on iPhone in standalone mode.

### 2. Loading State Standardization

Multiple loading patterns exist: custom `border-b-2 border-primary` spinners, `Loader2` icon, and `SettingsPageSkeleton`. Standardize to one approach.

**Files**: `frontend/app/(dashboard)/user/layout.tsx`, `frontend/app/(dashboard)/configuration/layout.tsx`, `frontend/app/(dashboard)/configuration/page.tsx`, `frontend/components/ui/settings-page-skeleton.tsx`

- [x] **Replace all raw spinner divs with `Loader2`** — User layout (line 24), config layout loading state, and config landing page all use a custom `border-b-2 border-primary` spinner div instead of the `Loader2` component from lucide-react
- [x] **Ensure all settings pages use `SettingsPageSkeleton`** — Some config pages use inline spinners or generic messages; standardize initial load to `SettingsPageSkeleton`

> **Mobile Impact**: LOW — Visual consistency only. No functional impact.

### 3. Native Checkbox Replacement

The full notifications page uses `<input type="checkbox">` instead of the shadcn `Checkbox` component — the only place in the app that does this.

**Files**: `frontend/app/(dashboard)/notifications/page.tsx`

- [x] **Replace native `<input type="checkbox">` with shadcn `Checkbox`** — Lines 323-327 and 334-341 use native checkboxes; the shadcn `Checkbox` is already imported and used elsewhere in the app (e.g., preferences page)

> **Mobile Impact**: MEDIUM — Native checkboxes render differently across browsers and are small touch targets on mobile. shadcn `Checkbox` has consistent 16px hit area with proper focus ring.

### 4. Avatar Component Inconsistency

The admin user table uses a custom `div` with `bg-primary/10` for fallback avatars instead of the shadcn `Avatar`/`AvatarFallback` component used everywhere else. Also only shows first letter instead of two initials.

**Files**: `frontend/components/admin/user-table.tsx`, `frontend/components/user-dropdown.tsx`, `frontend/app/(dashboard)/user/profile/page.tsx`

- [x] **Replace custom avatar div with shadcn `Avatar`/`AvatarFallback`** in user-table.tsx (line 192-196) to match user-dropdown.tsx and profile page
- [x] **Show two initials** instead of `user.name.charAt(0)` — use the same `getInitials()` logic
- [x] **Extract `getInitials()` to a shared utility** — Currently duplicated in `user-dropdown.tsx` (line 32-39) and `profile/page.tsx` (line 110-117); move to `frontend/lib/utils.ts`

> **Mobile Impact**: LOW — Visual consistency.

### 5. Notification Item Mark-Read Button

The mark-read action in notification items uses a custom `role="button"` div instead of the shadcn `Button` component.

**Files**: `frontend/components/notifications/notification-item.tsx`

- [x] **Replace custom `role="button"` div with shadcn `Button`** (line 104-113) — Use `variant="ghost" size="icon"` for consistency; simplifies keyboard handling since `Button` handles Enter/Space natively
- [x] **Reduce mark-read button size in compact (dropdown) mode** — `h-11 w-11` is oversized for the dropdown context; use `h-8 w-8` in compact mode while keeping `h-11 w-11` for the full page (meets 44px touch target)

> **Mobile Impact**: MEDIUM — Proper `Button` component ensures consistent focus states and touch target behavior.

### 6. Help Link Icon

**Files**: `frontend/components/help/help-link.tsx`

- [x] **Replace `ExternalLink` icon with `HelpCircle` or `BookOpen`** — `ExternalLink` implies opening a new browser tab, but clicking opens an in-app modal; misleading on mobile where external links have different navigation expectations

> **Mobile Impact**: MEDIUM — On mobile, users expect `ExternalLink` to leave the app; using `HelpCircle` correctly signals in-app help.

---

## Phase 2: High Impact — Visual Hierarchy & Mobile UX

Items that significantly improve the visual experience, especially on mobile and PWA.

### 7. Login Page

The auth layout is a bare centered card on `bg-muted` — functional but forgettable. This is the first thing users see and the first screen on PWA install.

**Files**: `frontend/components/auth/auth-page-layout.tsx`, `frontend/app/(auth)/login/page.tsx`

**shadcn block reference**: Align with **login-02** (split-panel with cover image on desktop, single column on mobile)

- [x] **Add split-panel layout on desktop** — Decorative side panel (branded gradient, illustration, or pattern) on the left, form on the right. Use `hidden lg:block` for the decorative panel so it gracefully degrades
- [x] **Keep mobile as full-width centered card** — Current `min-h-svh` + centered card pattern is correct for mobile; don't add complexity
- [x] **Add subtle entrance animation** — Fade-in + slight slide-up on the card using `tailwindcss-animate` utilities (`animate-in fade-in slide-in-from-bottom-4 duration-500`)
- [x] **Ensure form inputs meet touch target sizing** — Current inputs are `h-9` (36px); consider `h-10` (40px) for auth forms specifically where accuracy matters on mobile

> **PWA Impact**: HIGH — Login is the first screen after PWA install. A polished login creates the impression of a native app, not a web wrapper.

**Related**: [Auth UI Redesign Roadmap](auth-ui-redesign-roadmap.md) Phase 3

> **Note**: Auth pages currently use the shadcn **login-03** pattern (centered card on `bg-muted`). The split-panel upgrade targets **login-02**. Keep mobile as-is; only add the decorative panel on `lg:` breakpoint.

### 8. Dashboard

The dashboard is `space-y-6` with basic cards using `grid-cols-1 md:grid-cols-2 lg:grid-cols-3`. No visual hierarchy guiding the eye.

**Files**: `frontend/app/(dashboard)/dashboard/page.tsx`, `frontend/components/dashboard/widgets/`

**shadcn block reference**: Align with **dashboard-01** (section cards with prominent metrics, chart area, data summary)

- [x] **Give the welcome widget hero treatment** — Full-width, larger padding, Newsreader heading font, subtle gradient or accent background. On mobile this should be the dominant first element
- [x] **Make stats cards visually distinct** — Add colored accent borders or icon containers with `bg-primary/10` backgrounds per stat type, matching dashboard-01's section-cards pattern
- [x] **Improve empty/loading states** — Use skeleton cards matching the final layout shape (3-column grid of skeleton cards) instead of a single spinner
- [x] **Add staggered entrance animation** — Use `tailwindcss-animate` with `animation-delay` custom property for cards: `animate-in fade-in slide-in-from-bottom-2`
- [x] **Mobile card optimization** — On mobile (single column), cards should be full-bleed with reduced horizontal padding for maximum content area

> **PWA Impact**: HIGH — Dashboard is the PWA home screen. It needs to feel like a native app home screen, not a basic admin panel.

### 9. Color-Coded Notification Icons

The single biggest visual improvement for minimal code change. Currently all notification icons are monochrome `text-muted-foreground`.

**Files**: `frontend/lib/notification-types.ts`, `frontend/components/notifications/notification-item.tsx`

- [x] **Add color mapping to `NotificationTypeMeta`** — Add optional `color` field: green (`text-green-600`) for success (backup completed, payment succeeded), red (`text-red-600`) for errors/security, amber (`text-amber-600`) for warnings (quota, storage), blue (`text-blue-600`) for info (system update)
- [x] **Apply color to icon container** — In notification-item.tsx, wrap icon in a `rounded-full p-1.5` container with matching `bg-{color}/10` background for visual pop
- [x] **Improve unread indicator** — Add a small `h-2 w-2 rounded-full bg-primary` dot on the left side of unread items instead of just `bg-muted/50` background change

> **Mobile Impact**: HIGH — Color coding makes notifications scannable at a glance on small screens where users triage quickly. Especially important for PWA push notification follow-up (user taps notification, lands in dropdown).

### 10. Branding Page Touch & Safety Fixes

**Files**: `frontend/app/(dashboard)/configuration/branding/page.tsx`

- [x] **Make image delete button always visible** — Delete (X) icon on uploaded logos only appears on hover (invisible on touch devices). Add a persistent small destructive button below/beside the preview, or use a `Button` with `variant="ghost" size="icon"` that's always rendered
- [x] **Add confirmation to "Reset to Defaults"** — Currently resets ALL branding at once with no dialog. Add an `AlertDialog` confirmation: "This will reset your logo, colors, and custom CSS to defaults."

> **PWA Impact**: MEDIUM — Touch devices are the primary PWA platform. Hover-only interactions are fundamentally broken on touch.

### 11. Help Center Mobile Navigation

The help sidebar is `hidden md:block`, so mobile users viewing an article have no way to browse within a category without going back to the grid.

**Files**: `frontend/components/help/help-center-modal.tsx`, `frontend/components/help/help-sidebar.tsx`

- [x] **Add mobile category selector** — When viewing an article on mobile, show a horizontal tab bar or dropdown above the content area listing categories, replacing the hidden desktop sidebar
- [x] **Keep search accessible** — Search disappears when viewing an article; add a persistent search icon/trigger in the modal header that's always visible regardless of state
- [x] **Add transition between states** — Switching from category grid to article view is instant; add a crossfade or slide transition using `tailwindcss-animate`

> **PWA Impact**: HIGH — Help center is a key feature for self-service support. On mobile PWA, the hidden sidebar creates a dead-end navigation pattern that forces users to repeatedly go back.

### 12. Sidebar & Navigation

The main sidebar uses a proper Sheet drawer on mobile (good) with 44px touch targets (good). Visual polish items remain.

**Files**: `frontend/components/sidebar.tsx`

**shadcn block reference**: Current pattern matches **sidebar-07** (collapsible to icons). Maintain this pattern.

- [x] **Replace `bg-muted` active state with primary accent** — Use a colored left border (`border-l-2 border-primary`) or primary-tinted background (`bg-primary/5`) for the active nav item
- [x] **Add hover animation** — Subtle scale or background transition on nav items: `transition-colors duration-150`
- [x] **Improve collapsed-state tooltips** — When sidebar is collapsed to icons, ensure tooltip appears on hover/focus showing the full nav label
- [x] **Consider subtle sidebar background tint** — A slight `bg-muted/50` or `bg-sidebar` background to visually separate from main content area

> **Mobile Impact**: LOW — Mobile uses Sheet drawer which already handles active states well. These are primarily desktop improvements.

### 13. Header

Functional with good responsive breakpoints. Mobile shows hamburger + search icon + notification bell. Desktop shows full controls with backdrop blur.

**Files**: `frontend/components/header.tsx`

- [x] **Remove vertical separator dividers** — Replace `Separator` components between action groups with `gap-2` spacing for a cleaner, less cluttered look
- [x] **Add hover micro-interactions** — Subtle `transition-transform duration-150 hover:scale-105` on icon buttons for tactile feedback

> **Mobile Impact**: LOW — Header already handles mobile well with conditional rendering. Separators take horizontal space on smaller tablet screens.

---

## Phase 3: Medium Impact — Page-Level Improvements

Structural improvements to individual pages that improve usability, especially on mobile where vertical scrolling is the primary interaction.

### 14. Configuration Navigation Sidebar

Well-structured with 6 collapsible groups, permission filtering, and responsive mobile drawer. Visual and structural issues remain.

**Files**: `frontend/app/(dashboard)/configuration/layout.tsx`

**shadcn block reference**: Current pattern loosely matches **sidebar-05** (collapsible submenus). Should align more closely.

- [x] **Strengthen active state** — Current `bg-muted text-foreground font-medium border border-border` barely stands out from hover. Use `bg-primary/10 text-primary font-medium border-l-2 border-primary` for clear active indication
- [x] **Animate chevron rotation** — Replace separate `ChevronDown`/`ChevronRight` icons with a single `ChevronDown` using `transition-transform duration-200` and `rotate-[-90deg]` when collapsed
- [x] **Simplify navigation items** — Description text (`text-xs text-muted-foreground`) under each nav item adds visual noise; hide by default, show on hover via `group-hover:block` or move to tooltip
- [x] **Replace raw loading spinner** — Config landing page and layout loading state use custom spinner; standardize to `Loader2`

> **Mobile Impact**: MEDIUM — Mobile drawer with 40px+ touch targets is already solid. Chevron animation and active state improvements benefit both platforms. Description text is especially noisy on the mobile drawer where vertical space is limited.

### 15. User Preferences Page

The most complex user-facing page. PWA install section is already well-implemented with device-specific instructions, but the overall page is overwhelming.

**Files**: `frontend/app/(dashboard)/user/preferences/page.tsx`

- [x] **Add tab navigation** — Break page into tabs: "Appearance", "Defaults & Regional", "Notifications", "PWA & Devices". Use shadcn `Tabs` component with `TabsList` + `TabsTrigger` + `TabsContent`. On mobile, tabs should scroll horizontally with `overflow-x-auto`
- [x] **Add visual hierarchy between sections** — Give the appearance/theme section more visual weight (accent top border `border-t-2 border-primary`). Notification type matrix already collapsed by default
- [x] **Consistent icon usage** — All section headers already have icons (Palette, Bell, Brain, Globe, Download). Verified complete
- [x] **Mobile notification type matrix** — Stacked card layout per notification type on mobile via `useIsMobile()`, table layout preserved on desktop

> **PWA Impact**: HIGH — This page contains the PWA install section and push notification settings. Users configuring their PWA experience shouldn't have to scroll through 15+ cards to find the install button.

### 16. User Security Page

Five full-height cards stacked with no hierarchy. Users must scroll extensively to review their security posture.

**Files**: `frontend/app/(dashboard)/user/security/page.tsx`, `frontend/components/user/security/`

- [x] **Add security status overview** — `SecurityOverview` component at top with 2FA status, passkey count, SSO connections, API key count in a responsive grid with color-coded icons
- [x] **Use `PasswordInput` component** — All three password fields in `password-section.tsx` now use `PasswordInput` with show/hide toggle
- [x] **Collapse less-used sections by default** — Sessions (`sessions-section.tsx`) and API Keys (`api-keys-section.tsx`) converted to `CollapsibleCard defaultOpen={false}`

> **Mobile Impact**: MEDIUM — Long scrolling pages are worse on mobile. Collapsing sections and adding the status overview reduces scroll distance significantly.

### 17. User Profile Page

**Files**: `frontend/app/(dashboard)/user/profile/page.tsx`

- [x] **Hero-style avatar section** — Centered 112px avatar with name/email below, `bg-muted/30 rounded-xl` background tint, native app profile feel
- [x] **Add avatar upload** — Camera icon overlay on hover (desktop) / persistent edit button (mobile). Backend `POST/DELETE /api/profile/avatar` endpoints, `AvatarUpload` component with instant preview, client-side validation (2MB, image types), SSO-safe delete logic, user deletion cleanup
- [x] **Group memberships context** — Admin users see "Manage groups" link and clickable group badges linking to `/configuration/groups`; `UsersRound` icon added to card title

> **PWA Impact**: MEDIUM — Profile is a frequently visited page in PWA. Hero avatar section creates the "native app" feel users expect.

### 18. Notification Dropdown

Already uses the correct Popover (desktop) / Sheet (mobile) pattern. Content improvements needed.

**Files**: `frontend/components/notifications/notification-dropdown.tsx`

- [x] **Compact timestamps** — Timestamp rendered inline with title (right-aligned `ml-auto`) in compact mode, separate line in full page mode
- [x] **Animate dropdown items** — Staggered `animate-in fade-in slide-in-from-bottom-1` with 50ms incremental delay per item
- [x] **Improve empty state** — `font-heading` on primary message, descriptive subtext "When you receive notifications, they'll appear here."

> **Mobile Impact**: MEDIUM — On mobile this renders as a Sheet (good). Compact timestamps and staggered animation improve the mobile sheet experience.

### 19. Full Notifications Page

**Files**: `frontend/app/(dashboard)/notifications/page.tsx`

- [x] **Add date grouping** — `groupByDate()` helper buckets notifications into "Today", "Yesterday", "Earlier this week", "Older" with section headers
- [x] **Add total notification count** — "Showing X–Y of Z notifications" displayed above pagination controls
- [x] **Tighten filter bar gap** — `mt-6` reduced to `mt-4` on both TabsContent elements

> **Mobile Impact**: LOW — Date grouping improves scannability on all devices. Count helps mobile users understand list length without scrolling to the bottom.

### 20. Notification Bell & Badge

**Files**: `frontend/components/notifications/notification-bell.tsx`

- [x] **Fix ping animation overlap** — Badge and ping wrapped in shared container; ping uses `absolute inset-0` to emanate from the badge dimensions instead of competing at the corner

> **Mobile Impact**: LOW — Visual polish only.

### 21. Admin User Management

**Files**: `frontend/app/(dashboard)/configuration/users/page.tsx`, `frontend/components/admin/user-table.tsx`

- [x] **Verify "success" badge variant** — Verified: `variant="success"` is defined in `badge.tsx` as `border-transparent bg-green-500 text-white`. No change needed
- [x] **Improve pagination** — Already implemented: "Showing X-Y of Z users" exists at line 188-189 of users page
- [x] **Consider bulk actions** — Already implemented: multi-select with bulk enable/disable/delete in `user-table.tsx` lines 158-211, 393-432

> **Mobile Impact**: LOW — Table already uses `overflow-x-auto` and hides groups column on mobile. Pagination text helps mobile users.

---

## Phase 4: Polish — Typography, Animation & Visual Warmth

Items that add personality and refinement. Lower priority but contribute to the overall "feels like a real product" quality.

### 22. Typography — Newsreader Usage

Inter + Newsreader is a solid pairing, but Newsreader (the serif heading font) is underused — it's applied via CSS variable but rarely visible in practice.

**Files**: `frontend/config/fonts.ts`, `frontend/app/globals.css`

- [x] **Use Newsreader prominently in welcome greeting** — Dashboard welcome widget heading already uses `font-heading`; added to dashboard section headings
- [x] **Audit heading usage** — CSS variable `--font-heading` already applies to all h1-h6 via `globals.css` line 122
- [x] **Consider Newsreader for empty states** — Added `font-heading` to empty states in notification list, audit log, and file browser

> **Mobile Impact**: LOW — Typography improvements are platform-agnostic.

### 23. Help Article Rendering

**Files**: `frontend/components/help/help-article.tsx`, `frontend/app/globals.css` (help-article-prose)

- [x] **Increase heading sizes** — Bumped to `prose-h1:text-2xl prose-h2:text-xl prose-h3:text-lg`; added `font-heading` to all headings
- [x] **Use foreground color for body text** — Changed paragraphs and lists from `text-muted-foreground` to `text-foreground`
- [x] **Add syntax highlighting** — Added `rehype-highlight` with highlight.js; registered `bash`, `javascript`, `json`, `graphql` languages. Theme-aware token colors via CSS custom properties in `globals.css` (works across all 18 themes)
- [x] **Add table of contents** — `extractHeadings()` utility extracts h1-h3 from markdown; shown for articles with 3+ headings. Desktop: sticky 192px right sidebar. Mobile: collapsible "On this page" section above article. Active heading tracking via IntersectionObserver

> **Mobile Impact**: MEDIUM — Heading sizes and text color directly affect readability on small screens. Muted text on mobile in variable lighting conditions is harder to read.

### 24. Help Search

**Files**: `frontend/components/help/help-search.tsx`

- [x] **Add keyboard navigation** — Arrow-key through results, Enter to select, Escape to close; ARIA combobox pattern with `aria-activedescendant`
- [x] **Highlight matched text** — Fuse.js `includeMatches` enabled; title matches highlighted with `<mark>` and `bg-yellow-200/60`
- [x] **Reduce clear button size** — Reduced from `h-11 w-11` to `h-7 w-7`
- [x] **Visual separation** — Added `shadow-md` to results dropdown for stronger visual boundary

> **Mobile Impact**: LOW — Keyboard navigation is desktop-only. Match highlighting and visual separation benefit all platforms.

### 25. Help Sidebar

**Files**: `frontend/components/help/help-sidebar.tsx`

- [x] **Use shadcn `Button` component** — Replaced plain `<button>` elements with shadcn `Button variant="ghost"` for both category and article items
- [x] **Animate article list expand/collapse** — Wrapped in `Collapsible` + `CollapsibleContent` with `animate-in/animate-out` transitions
- [x] **Show article count on categories** — Added `Badge variant="secondary"` with article count next to each category chevron

> **Mobile Impact**: LOW — Sidebar is hidden on mobile (replaced by category grid). These are desktop improvements.

### 26. Help Landing Page Visual Warmth

**Files**: `frontend/components/help/help-center-modal.tsx`

- [x] **Add visual warmth to category cards** — Added `bg-primary/10 rounded-lg` icon containers with hover transition on category cards
- [x] **Use Newsreader for "How can we help?" heading** — Applied `font-heading` class

> **Mobile Impact**: LOW — Category grid is the mobile navigation pattern, so making it more inviting benefits mobile users directly.

### 27. Configuration Cards & Forms

**Files**: `frontend/components/ui/settings-switch-row.tsx`, `frontend/components/ui/collapsible-card.tsx`

- [x] **Add card visual differentiation** — Added `intent` prop to `CollapsibleCard`: `"danger"` applies `border-destructive/30`, `"info"` applies `border-primary/20`
- [x] **Test CollapsibleCard badge on mobile** — Badge uses `flex-wrap` and `shrink-0` for proper wrapping on narrow screens
- [x] **Consider `space-y-3` for toggle-only cards** — Documented as convention in `ui-patterns.md`; left default at `space-y-4` for consistency

> **Mobile Impact**: MEDIUM — Badge cramping is specifically a mobile issue.

### 28. Audit Log & Data Tables

The most data-dense configuration page. Horizontal-scrollable table works but the filter+table combo is cramped on mobile.

**Files**: `frontend/app/(dashboard)/configuration/audit/page.tsx`

- [x] **Mobile filter drawer** — Filters render in a bottom Sheet on mobile (via `useIsMobile()`), with "Active" badge when filters are set
- [x] **Strengthen real-time highlight** — Added `border-l-4 border-l-primary` flash with 3-second fade-out transition
- [x] **Unify severity badges** — Severity badges already use `Badge variant="outline"` with custom CSS color classes; pattern is consistent
- [x] **Distinguish non-sortable headers** — Applied `text-muted-foreground font-medium` (instead of default bold) to all table headers

> **PWA Impact**: MEDIUM — PWA users on mobile tablets will frequently access audit logs. The filter drawer pattern is essential for usable data tables on touch devices.

### 29. About Dialog

**Files**: `frontend/components/about-dialog.tsx`

- [x] **Add app logo/branding** — Added `Logo variant="full" size="lg"` centered at the top of the dialog
- [x] **Link to changelog** — Version number is a clickable `Link` to `/configuration/changelog`
- [x] **Hide technical details from non-admins** — System Information section wrapped in `Collapsible`, expanded by default for admins, collapsed for regular users

> **Mobile Impact**: LOW — Dialog renders correctly on mobile already.

### 30. Configuration Page Consistency

Cross-cutting consistency issues across all ~20 configuration pages.

**Files**: All `frontend/app/(dashboard)/configuration/*/page.tsx`

- [x] **Standardize state management** — Documented convention in `ui-patterns.md`: form-based settings use `react-hook-form`, display/action pages use `useState`
- [x] **Standardize error handling** — Documented convention: toast for action results, inline `Alert` for persistent errors
- [x] **Consistent page header spacing** — Verified all pages use `space-y-6`; documented as convention

> **Mobile Impact**: LOW — Consistency improvements, not mobile-specific.

### 31. SSO & Complex Pages

**Files**: `frontend/app/(dashboard)/configuration/sso/page.tsx`, `frontend/app/(dashboard)/configuration/ai/page.tsx`

- [x] **Extract SSO provider cards** — SSO page broken into `SSOGlobalOptionsCard`, `SSOProviderCard`, `SSOOidcCard` + shared types (page now 263 lines)
- [x] **Audit AI/LLM page componentization** — AI page delegates to `OrchestrationModeCard`, `ProviderListCard`, `ProviderDialog`, `AISettingsForm`; `ProviderDialog` further decomposed into `ProviderCredentialFields`, `ProviderModelSelection`, and `model-cache` utilities (dialog 732→434 lines)
- [x] **Consistent provider card pattern** — Both SSO and AI already use `CollapsibleCard` with status badges

> **Mobile Impact**: LOW — Code organization improvements. The `CollapsibleCard` pattern already handles mobile well.

### 32. User Dropdown

**Files**: `frontend/components/user-dropdown.tsx`

- [x] **Soften logout confirmation** — Replaced `AlertDialog` with standard `Dialog` and non-destructive `Button` styling
- [x] **Inline admin badge** — Moved admin badge inline next to name (removed `flex-col` wrapper)

> **Mobile Impact**: LOW — User dropdown trigger already hides text on mobile, showing avatar only.

---

## Key Files

| Area | Files |
|------|-------|
| Auth layout | `frontend/components/auth/auth-page-layout.tsx` |
| Dashboard | `frontend/app/(dashboard)/dashboard/page.tsx`, `frontend/components/dashboard/widgets/` |
| Sidebar | `frontend/components/sidebar.tsx` |
| Header | `frontend/components/header.tsx` |
| Typography | `frontend/config/fonts.ts`, `frontend/app/globals.css` |
| Notification bell | `frontend/components/notifications/notification-bell.tsx` |
| Notification dropdown | `frontend/components/notifications/notification-dropdown.tsx` |
| Notification item | `frontend/components/notifications/notification-item.tsx` |
| Notification types | `frontend/lib/notification-types.ts` |
| Notifications page | `frontend/app/(dashboard)/notifications/page.tsx` |
| User dropdown | `frontend/components/user-dropdown.tsx` |
| User profile | `frontend/app/(dashboard)/user/profile/page.tsx` |
| User preferences | `frontend/app/(dashboard)/user/preferences/page.tsx` |
| User security | `frontend/app/(dashboard)/user/security/page.tsx`, `frontend/components/user/security/` |
| User layout | `frontend/app/(dashboard)/user/layout.tsx` |
| Admin users | `frontend/app/(dashboard)/configuration/users/page.tsx`, `frontend/components/admin/user-table.tsx` |
| Config layout | `frontend/app/(dashboard)/configuration/layout.tsx` |
| Config pages | `frontend/app/(dashboard)/configuration/*/page.tsx` |
| Settings components | `frontend/components/ui/settings-switch-row.tsx`, `frontend/components/ui/settings-page-skeleton.tsx`, `frontend/components/ui/collapsible-card.tsx` |
| SSO | `frontend/app/(dashboard)/configuration/sso/page.tsx` |
| AI/LLM | `frontend/app/(dashboard)/configuration/ai/page.tsx` |
| Audit log | `frontend/app/(dashboard)/configuration/audit/page.tsx` |
| Branding | `frontend/app/(dashboard)/configuration/branding/page.tsx` |
| Help modal | `frontend/components/help/help-center-modal.tsx` |
| Help sidebar | `frontend/components/help/help-sidebar.tsx` |
| Help article | `frontend/components/help/help-article.tsx` |
| Help search | `frontend/components/help/help-search.tsx` |
| Help link | `frontend/components/help/help-link.tsx` |
| Help content | `frontend/lib/help/help-content.ts` |
| About dialog | `frontend/components/about-dialog.tsx` |

## Dependencies

- **None required** — All Phase 1-4 implemented changes use existing shadcn/ui components and Tailwind utilities
- **Added for Item 23**: `rehype-highlight` (installed) for code syntax highlighting in help articles
- ~~**Backend required for Item 17**: Avatar upload endpoint for user profile~~ (completed)
- **Not required**: framer-motion — `tailwindcss-animate` (already installed) provides sufficient animation utilities for all items in this roadmap

## Implementation Guidance

Key patterns and anti-patterns to follow during implementation:

| What | Where |
|------|-------|
| Avatar/initials | [ui-patterns.md — Avatar & Initials](../ai/patterns/ui-patterns.md#avatar--initials) |
| Loading states | [ui-patterns.md — Loading States](../ai/patterns/ui-patterns.md#loading-states) |
| Entrance animations | [ui-patterns.md — Entrance Animations](../ai/patterns/ui-patterns.md#entrance-animations) |
| Newsreader font usage | [ui-patterns.md — Typography](../ai/patterns/ui-patterns.md#typography--newsreader-serif-heading-font) |
| Notification color coding | [ui-patterns.md — Notification Icons](../ai/patterns/ui-patterns.md#notification-icons--color-coding) |
| PWA safe area insets | [ui-patterns.md — PWA Safe Area](../ai/patterns/ui-patterns.md#pwa-safe-area-insets) |
| PasswordInput component | [ui-patterns.md — PasswordInput](../ai/patterns/ui-patterns.md#passwordinput) |
| Hover-only anti-pattern | [responsive.md — Hover-only](../ai/anti-patterns/responsive.md#dont-use-hover-only-deleteaction-buttons-on-touch) |
| Avatar anti-pattern | [frontend.md — Avatar](../ai/anti-patterns/frontend.md#dont-use-a-raw-div-for-avatar-fallback) |
| Checkbox anti-pattern | [frontend.md — Checkbox](../ai/anti-patterns/frontend.md#dont-use-native-input-typecheckbox-in-app-ui) |

## Success Criteria

### PWA & Mobile
- [x] App header and bottom actions respect iOS safe area insets in standalone mode
- [x] All interactive elements meet 44px minimum touch target on mobile
- [x] No hover-only interactions (all hover states have touch-accessible alternatives)
- [x] Help center is fully navigable on mobile without desktop sidebar
- [x] Notification dropdown is scannable on mobile Sheet with color-coded icons
- [x] Preferences page PWA/notification sections are accessible without excessive scrolling
- [x] Audit log filters are usable on mobile via drawer pattern

### Component Consistency
- [x] All checkboxes use shadcn `Checkbox` component (no native inputs)
- [x] All avatars use shadcn `Avatar`/`AvatarFallback` with consistent `getInitials()` utility
- [x] All loading states use `Loader2` or `SettingsPageSkeleton` (no custom spinner divs)
- [x] All buttons use shadcn `Button` (no custom `role="button"` divs)
- [x] Help sidebar uses shadcn `Button` and `Collapsible` components

### Visual Design
- [x] Login page creates a strong first impression aligned with login-02 block pattern
- [x] Dashboard has clear visual hierarchy aligned with dashboard-01 block pattern
- [x] Notifications are scannable at a glance (color-coded, clear read/unread states)
- [x] Newsreader font is visible and adds character in welcome, headings, and empty states
- [x] Config nav active state is clearly distinguishable with primary color accent
- [x] All changes respect the multi-theme system (work across all 18 color themes)
- [x] No regression in functionality, accessibility, or mobile responsiveness
