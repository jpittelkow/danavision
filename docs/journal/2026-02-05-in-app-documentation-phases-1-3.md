# In-App Documentation & Onboarding (Phases 1-3) - 2026-02-05

## Overview

Implemented the first three phases of the In-App Documentation & Onboarding roadmap: Getting Started Wizard, Contextual Tooltips, and Help Documentation Center. These features provide a comprehensive in-app help system to improve user experience and reduce support burden.

## Implementation Approach

### Phase 1: Getting Started Wizard

Created a multi-step onboarding wizard that appears for new users:

- **Backend Infrastructure:**
  - `user_onboarding` table to track wizard completion status per user
  - `UserOnboarding` model with methods for step tracking, completion, and dismissal
  - `OnboardingController` with endpoints for status, completion, step progress, and reset

- **Frontend Components:**
  - `WizardProvider` context for global wizard state management
  - `WizardModal` component with step navigation and progress indicators
  - 7 individual step components (Welcome, Profile, Security, Notifications, Theme, Tour, Completion)
  - Integration via `AppShell` provider and "Getting Started" menu item in user dropdown

### Phase 2: Contextual Tooltips

Implemented field-level help throughout settings pages:

- **Components:**
  - Radix UI Tooltip wrapper (`@/components/ui/tooltip.tsx`)
  - `HelpTooltip` component with help circle icon and hover content
  - Extended `FormField` and `SettingsSwitchRow` with optional `tooltip` prop

- **Content Management:**
  - Centralized `tooltip-content.ts` with organized sections (security, sso, ai, system, backup, storage)
  - `getTooltip()` helper for safe access with fallback

- **Integration:**
  - Added tooltips to Security, SSO, AI, System, Backup, and Storage settings pages

### Phase 3: Help Documentation Center

Created a searchable in-app help system:

- **Components:**
  - `HelpProvider` context with keyboard shortcuts (? and Ctrl+/)
  - `HelpCenterModal` with sidebar navigation and article content area
  - `HelpSidebar` for category/article navigation
  - `HelpSearch` with Fuse.js client-side search
  - `HelpArticle` with react-markdown rendering and remark-gfm support
  - `HelpIcon` for header and `HelpLink` for contextual links

- **Content Structure:**
  - User-facing categories: Getting Started, Your Account, Security, Notifications
  - Admin-only categories: Admin Settings, Search Administration
  - 13 help articles covering key features

- **Integration:**
  - Help icon in header (opens modal)
  - Help Center menu item in user dropdown
  - HelpLink components on security settings pages

## Challenges Encountered

1. **npm install in Windows PowerShell**: The `cd folder && npm install` pattern doesn't work in PowerShell. Used the `working_directory` parameter instead.

2. **Provider Nesting**: Had to ensure `HelpProvider` is placed inside `SearchProvider` but wraps `WizardProvider` to allow both systems to function independently.

3. **Role-Based Content**: Admin-only help categories required checking user's admin status in the help content functions.

## Observations

- The wizard state is persisted per-user in the database, surviving logouts and browser changes
- Keyboard shortcuts (?) only trigger when not typing in form fields
- The search uses Fuse.js for fuzzy matching without server-side requirements
- Help articles render markdown with GitHub-flavored markdown support (tables, task lists)

## Trade-offs

1. **Static Content vs Database**: Help content is stored in TypeScript files rather than database. This is simpler but requires code deployment for content updates. Phase 6 (optional) could add admin content editing.

2. **Modal vs Page**: Help center is implemented as a modal dialog rather than a dedicated page. This provides quick access from anywhere but limits the amount of content that can be displayed comfortably.

3. **Tooltip Content Centralization**: All tooltip content is in one file. This makes it easy to find and update but could become large. Could be split per-section if needed.

## Next Steps (Future Considerations)

- **Phase 4 (Interactive Tutorials)**: Add driver.js or similar for step-by-step guided tours
- **Phase 5 (Help Widget)**: Floating help button with context-aware suggestions
- **Phase 6 (Admin Content Management)**: UI for admins to edit tooltips and help articles
- **Additional Articles**: Branding, Email Configuration, SSO setup, AI/LLM configuration
- **Analytics**: Track which help articles are most viewed

## Testing Notes

1. **Wizard Testing:**
   - New users should see the wizard on first login
   - "Getting Started" in user dropdown should reset and show wizard
   - Progress should persist across page refreshes
   - Dismiss and complete states should be tracked

2. **Tooltip Testing:**
   - Hover over help icons on settings pages
   - Verify content appears in styled tooltip
   - Check keyboard accessibility

3. **Help Center Testing:**
   - Press `?` key to open/close help center (not when focused on input)
   - Search for articles using the search bar
   - Navigate categories and articles via sidebar
   - Verify admin categories only appear for admin users
   - Test "Learn more" links on settings pages

## Files Created/Modified

### Backend
- `backend/database/migrations/2026_02_05_000023_create_user_onboarding_table.php`
- `backend/app/Models/UserOnboarding.php`
- `backend/app/Http/Controllers/Api/OnboardingController.php`
- `backend/routes/api.php` (modified)
- `backend/app/Models/User.php` (modified - added onboarding relationship)

### Frontend Components
- `frontend/components/onboarding/` (wizard-provider, wizard-modal, wizard-step, 7 step components)
- `frontend/components/ui/tooltip.tsx`
- `frontend/components/ui/help-tooltip.tsx`
- `frontend/components/help/` (help-provider, help-center-modal, help-article, help-sidebar, help-search, help-icon, help-link)

### Frontend Libraries
- `frontend/lib/tooltip-content.ts`
- `frontend/lib/help/help-content.ts`
- `frontend/lib/help/help-search.ts`

### Integration Points
- `frontend/components/app-shell.tsx` (added HelpProvider, WizardProvider)
- `frontend/components/header.tsx` (added HelpIcon)
- `frontend/components/user-dropdown.tsx` (added Help Center and Getting Started menu items)
- `frontend/components/providers.tsx` (added TooltipProvider)
- Various settings pages (added tooltips and HelpLinks)

### Dependencies Added
- `@radix-ui/react-tooltip`
- `react-markdown`
- `remark-gfm`
- `fuse.js`
