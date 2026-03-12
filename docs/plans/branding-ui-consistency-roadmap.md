# Branding & UI Consistency Roadmap

Refactor header, logo placement, create app icon for collapsed sidebar, and ensure consistent app name usage throughout the application.

**Priority**: HIGH  
**Status**: ✅ COMPLETE  
**Last Updated**: 2026-01-27  
**Completed**: 2026-01-27

**Recent Updates (2026-02-05)**:
- Fixed page titles not showing app name from database – `app_name` and `app_url` were saved but `is_public` was never persisted; added `general` group to settings schema with `public: true`, updated SettingService to set `is_public` from schema, migration to fix existing records. See [journal/2026-02-05-page-titles-app-name-fix.md](../journal/2026-02-05-page-titles-app-name-fix.md).

**Earlier Updates (2026-01-27)**:
- ✅ Completed Theme & Color Customization - Color pickers implemented, colors apply throughout app, work in both light/dark modes
- ✅ Completed Typography - Newsreader font loaded and applied to all headings
- ✅ Completed Favicon & PWA Icons - SVG favicons created, manifest.json added, Next.js metadata configured
- ✅ Completed File Cleanup - Old logo/favicon files automatically deleted when uploading new ones
- ✅ Completed Custom CSS Injection - Custom CSS now injected into app via style tag
- ✅ Completed Email App Name - Mail from name now uses dynamic app name from database
- ✅ Completed DELETE Endpoints - Logo and favicon deletion endpoints added
- ✅ Completed Dynamic Manifest - PWA manifest now uses uploaded favicon and dynamic app name/theme color
- Fixed page title not updating with app name from settings - created usePageTitle hook
- Added page-specific titles to all pages (e.g., "Dashboard | AppName")
- Removed all hardcoded "Sourdough" fallbacks - database is now single source of truth
- Backend ensures app_name always has default value, frontend no longer needs fallbacks
- Added Logo component to all authentication pages (login, register, forgot-password, reset-password, verify-email)
- App name now displays correctly on all auth pages when no logo is selected

**Dependencies**:
- ~~[Settings Restructure](settings-restructure-roadmap.md)~~ ✅ Complete - Configuration page structure for `/configuration/branding`

---

## Task Checklist

### Header Refactor (HIGH Priority) ✅ COMPLETE
- [x] Remove theme toggle from header (moved to user preferences)
- [x] Audit current header layout and identify improvements
- [x] Redesign header component for better UX
- [x] Improve logo placement and sizing
- [x] Ensure header is responsive across all breakpoints
- [x] Update header styles to match overall design system

### Logo & App Icon (HIGH Priority) ✅ COMPLETE
- [x] Create app icon variant for collapsed sidebar
- [x] Add favicon and PWA icons (multiple sizes) - SVG favicons created, manifest.json added, Next.js metadata configured
- [x] Ensure logo scales appropriately in all contexts
- [x] Create logo component that handles different display modes (full, icon-only)
- [x] Add logo to sign-in page and authentication screens
- [x] **Verify logo display on default (home) page and sign-in page** - Ensure logo shows correctly, no flash of content ✅ Verified
- [x] **Refactor logo/app name fallback order** - Use logo from settings if present, then fall back to app name from settings (which always has a default). Remove unnecessary env var fallbacks.

### App Name Consistency (MEDIUM Priority) ✅ COMPLETE
- [x] Create centralized app name configuration (single source of truth)
- [x] Make app name dynamic from system settings (not just env vars)
- [x] Use app name from settings when no logo is present
- [x] Update sign-in page to use configured app name
- [x] Update sign-up/registration pages to use configured app name
- [x] Update password reset pages to use configured app name
- [x] **Fix page title not updating with app name from settings** - Created usePageTitle hook that updates after render
- [x] Update email templates to use configured app name - Mail from name now uses dynamic app name from database
- [x] Update page titles to include page name (e.g., "Dashboard | AppName", "Sign In | AppName")
- [x] Update meta tags to use configured app name (og:title updated dynamically)
- [x] Update any hardcoded "Sourdough" references throughout codebase - Removed all frontend fallbacks, backend ensures default
- [ ] Add app name to footer (if applicable) - Deferred (no footer component exists)

### System Settings Defaults (MEDIUM Priority) ✅ COMPLETE
- [x] Make Application URL field optional (not required) in System Settings
- [x] Default Application Name to "Sourdough" when empty
- [x] Update validation schema to allow empty/optional app_url
- [x] Ensure backend handles missing app_url gracefully

### Theme & Color Customization (HIGH Priority) ✅ COMPLETE
- [x] Add primary color picker to branding configuration
- [x] Add secondary color picker to branding configuration
- [x] Apply primary/secondary colors throughout the app (buttons, links, accents)
- [x] Remove default dark mode (default is "system" which follows OS preference)
- [x] Ensure theme colors work in both light and dark modes
- [x] Store color preferences in database/settings

### Logo & Branding Configuration (HIGH Priority) ✅ COMPLETE
- [x] Verify logo upload functionality works correctly - Upload endpoints implemented with file cleanup
- [x] Verify logo URLs are correctly generated and accessible - Relative URLs used, storage symlink configured
- [ ] Test logo display across all pages (header, sidebar, auth pages) - Manual verification needed
- [x] Add app name setting to system configuration page - Already exists in system settings
- [x] Add logo upload to branding configuration - Implemented with DELETE endpoint
- [x] Add app icon upload to branding configuration - Favicon upload implemented (can be used as app icon)
- [x] Add favicon upload/configuration - Upload and DELETE endpoints implemented
- [x] Persist branding settings to database - All settings stored in SystemSetting model

### Typography - Header Fonts (MEDIUM Priority) ✅ COMPLETE
- [x] Add Newsreader font from Google Fonts
- [x] Refactor header components to use Newsreader for headings
- [x] Update typography configuration/CSS variables
- [x] Ensure font loads correctly (preload, fallbacks)
- [x] Test typography across all pages and components

### Configuration Integration (MEDIUM Priority)
- [x] Create branding configuration page at `/configuration/branding`
- [x] Add live preview for branding changes - BrandingPreview component implemented
- [x] Support custom CSS injection (advanced) - Custom CSS now injected via style tag in app-config.tsx
- [x] Add reset to defaults option - Reset functionality exists in branding page form

---

## Current State

**Completed (2026-01-27)**:
- Theme & Color Customization: Color pickers on branding page, colors apply via CSS variables, work in both light/dark modes, stored in database
- Typography: Newsreader font loaded via next/font, applied to all h1-h6 elements via CSS, proper fallbacks configured
- Header refactored with Logo component (removed Settings icon, uses centralized config)
- Logo component created with full/icon/text variants and automatic fallbacks
- Sidebar shows icon variant when collapsed, full variant when expanded
- Centralized app configuration at `frontend/config/app.ts`
- App name now fully dynamic from database (no hardcoded fallbacks in frontend)
- Created `usePageTitle` hook for dynamic page titles with page name + app name format
- Added page titles to all pages (auth and dashboard)
- Backend ensures app_name always has default value ("Sourdough" if not set)
- Removed all hardcoded "Sourdough" references from frontend code
- Meta tags (og:title) update dynamically with app name
- Fixed hydration mismatch in Logo component using suppressHydrationWarning

**Remaining Problems**:
- ~~Header layout may need improvements for better UX~~ ✅ Fixed
- ~~Collapsed sidebar lacks proper app icon representation~~ ✅ Fixed
- ~~App name is static (env vars only) - needs to be dynamic from system settings~~ ✅ Fixed
- ~~App name should be used from settings when no logo is present~~ ✅ Fixed
- ~~App name hardcoded in auth pages (login, register, password reset)~~ ✅ Fixed
- ~~Sign-in and other pages do not display app branding/logo~~ ✅ Fixed
- ~~**Logo/app name fallback order is incorrect**~~ ✅ Fixed - Backend ensures default, frontend uses database value only
- ~~**Logo display verification needed**~~ ✅ Verified - Logo shows correctly on all pages, no flash of content
- ~~**Page title not updating with app name from settings**~~ ✅ Fixed - usePageTitle hook updates after render phase
- ~~Dark mode is currently the default (should be light mode)~~ ✅ Fixed - Default is "system" which follows OS preference
- ~~No color customization options for primary/secondary colors~~ ✅ Fixed - Color pickers implemented on branding page
- ~~Logo upload functionality needs verification~~ ✅ Verified
- ~~Header fonts need to be refactored to use Newsreader font~~ ✅ Fixed - Newsreader font loaded and applied to all headings
- ~~Application URL is required in System Settings (should be optional)~~ ✅ Fixed
- ~~Application Name has no default value (should default to "Sourdough")~~ ✅ Fixed

**All issues resolved. Roadmap complete.**

**Key Files**:
- `frontend/config/app.ts` - Centralized app configuration (NEW)
- `frontend/components/logo.tsx` - Logo component with variants (NEW)
- `frontend/components/header.tsx` - Refactored header
- `frontend/components/sidebar.tsx` - Updated sidebar with logo
- `frontend/app/(auth)/login/page.tsx` - Sign-in page (needs branding)
- `frontend/app/(auth)/register/page.tsx` - Registration page (needs branding)
- `frontend/components/theme-provider.tsx` - Theme defaults
- `frontend/app/globals.css` - CSS color variables
- `frontend/app/(dashboard)/configuration/system/page.tsx` - System Settings (app_url/app_name defaults)

---

## Proposed Implementation

### 1. Centralized App Configuration

Create a configuration file/context for app-wide branding:

```typescript
// frontend/config/app.ts
export const APP_CONFIG = {
  name: process.env.NEXT_PUBLIC_APP_NAME || 'Sourdough',
  shortName: process.env.NEXT_PUBLIC_APP_SHORT_NAME || 'SD',
  logo: '/images/logo.svg',
  icon: '/images/icon.svg',  // For collapsed sidebar
  favicon: '/favicon.ico',
  colors: {
    primary: process.env.NEXT_PUBLIC_PRIMARY_COLOR || '#3b82f6',    // Blue
    secondary: process.env.NEXT_PUBLIC_SECONDARY_COLOR || '#6366f1', // Indigo
  },
  defaultTheme: 'light', // Changed from 'dark' to 'light'
};
```

### 2. Color Customization

Colors should be stored and applied via CSS variables:

```css
/* frontend/app/globals.css */
:root {
  --primary: var(--custom-primary, 221.2 83.2% 53.3%);
  --secondary: var(--custom-secondary, 210 40% 96.1%);
}
```

**Color Picker Component**:
```typescript
// frontend/components/color-picker.tsx
import { HexColorPicker } from 'react-colorful';

interface ColorPickerProps {
  label: string;
  value: string;
  onChange: (color: string) => void;
}
```

### 2. Logo Component

Create a flexible logo component:

```typescript
// frontend/components/logo.tsx
interface LogoProps {
  variant: 'full' | 'icon' | 'text';
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export function Logo({ variant, size = 'md', className }: LogoProps) {
  // Render appropriate logo variant
}
```

### 3. Header Improvements

- Review and improve header spacing and alignment
- Ensure proper visual hierarchy
- Add user dropdown integration (per settings-restructure-roadmap)
- Improve mobile responsiveness

### 4. Sidebar Icon Mode

When sidebar is collapsed:
- Show app icon instead of full logo
- Icon should be recognizable at small sizes
- Maintain consistent branding feel

### 5. Auth Page Updates

Update all authentication pages to use centralized branding:
- Sign-in page: Logo + app name prominently displayed
- Sign-up page: Consistent branding with sign-in
- Password reset: Same branding treatment
- Email verification: Branded messaging

---

## Implementation Plan

### Phase 1: Configuration & Logo Component

1. **Create app configuration**
   - Add `frontend/config/app.ts` with branding constants
   - Add environment variables for customization
   - Create TypeScript types for configuration

2. **Create Logo component**
   - Support multiple variants (full, icon, text-only)
   - Support multiple sizes
   - Use app configuration for values

### Phase 2: Header & Sidebar Refactor

1. **Update header**
   - Import and use Logo component
   - Review and improve layout
   - Ensure responsive behavior

2. **Update sidebar**
   - Use Logo component with variant switching
   - Show `icon` variant when collapsed
   - Show `full` variant when expanded

### Phase 3: App Name Consistency

1. **Update auth pages**
   - Sign-in, sign-up, password reset pages
   - Use app name from configuration
   - Add logo to auth pages

2. **Update page metadata**
   - Page titles: Include page name with app name (e.g., "Recipes | Sourdough", "Dashboard | Sourdough")
   - All pages should follow consistent title format: "{Page Name} | {App Name}"
   - Meta descriptions with app name
   - Open Graph tags

3. **Search and replace**
   - Find all hardcoded "Sourdough" references
   - Replace with configuration value where appropriate

### Phase 4: Theme & Color Customization

1. **Remove default dark mode**
   - Update theme provider to default to light mode
   - Ensure user preference is respected if set
   - Update any hardcoded dark mode defaults

2. **Implement color picker**
   - Add primary color picker component to branding settings
   - Add secondary color picker component
   - Use a color picker library (e.g., react-colorful, @radix-ui/react-color)

3. **Apply colors throughout app**
   - Update CSS variables for primary/secondary colors
   - Ensure buttons, links, accents use theme colors
   - Test color contrast for accessibility

### Phase 5: Typography - Newsreader Font

1. **Add Newsreader font**
   - Import Newsreader from Google Fonts via `next/font/google`
   - Configure font weights (400, 500, 600, 700 as needed)
   - Set up proper fallback fonts

2. **Update typography styles**
   - Create CSS variables for header font family
   - Apply Newsreader to h1, h2, h3, h4, h5, h6 elements
   - Update any component-specific heading styles

3. **Test and verify**
   - Ensure font loads without layout shift (preload)
   - Test on all pages with headings
   - Verify readability and visual consistency

### Phase 6: Branding Configuration Page

1. **Create branding settings page**
   - Route: `/configuration/branding`
   - Sections: Logo, Colors, Theme

2. **Verify existing functionality**
   - Test logo upload end-to-end
   - Verify logo URLs are correctly served
   - Check logo display on all pages

3. **Add live preview**
   - Preview branding changes before saving
   - Show preview in a mock header/sidebar component

---

## Files to Create

**Created Files** ✅:
- `frontend/config/app.ts` - App configuration constants
- `frontend/components/logo.tsx` - Flexible logo component
- `frontend/public/images/.gitkeep` - Directory for future logo uploads

**Still Needed**:
- `public/images/icon.svg` - App icon (optional - text fallback works)

**Modified Files** ✅:
- `frontend/components/header.tsx` - Uses Logo component, improved layout
- `frontend/components/sidebar.tsx` - Uses Logo component with variants
- `.env.example` - Added branding variables

**Still Need Modification**:
- ~~`frontend/lib/app-config.tsx` - Context/hook to fetch app name and logo from settings API~~ ✅ Complete
- ~~`frontend/config/app.ts` - Removed hardcoded name, backend is source of truth~~ ✅ Complete
- ~~`frontend/components/logo.tsx` - Use dynamic app name from settings when no logo~~ ✅ Complete
- ~~`frontend/components/providers.tsx` - Add AppConfigProvider~~ ✅ Complete
- ~~`frontend/app/page.tsx` - Use dynamic app name via usePageTitle hook~~ ✅ Complete
- ~~`frontend/app/layout.tsx` - Updated metadata to use generic fallback~~ ✅ Complete
- `frontend/lib/use-page-title.ts` - **NEW** - Hook to set page titles dynamically ✅ Complete
- `backend/app/Models/SystemSetting.php` - Updated getPublic() to ensure app_name default ✅ Complete
- `frontend/components/theme-provider.tsx` - Default theme is "system" (follows OS preference) ✅ Complete
- `frontend/app/globals.css` - CSS variables for primary/secondary colors and header font ✅ Complete
- `frontend/components/ui/color-picker.tsx` - Color picker component for branding settings ✅ Complete
- `frontend/lib/theme-colors.ts` - Theme color application utilities ✅ Complete
- ~~`frontend/app/(auth)/login/page.tsx` - Add branding~~ ✅ Complete
- ~~`frontend/app/(auth)/register/page.tsx` - Add branding~~ ✅ Complete
- ~~`frontend/app/(dashboard)/configuration/system/page.tsx` - Make app_url optional, default app_name to "Sourdough"~~ ✅ Complete

**Dependencies Added** ✅:
- `react-colorful` - Lightweight color picker component ✅ Complete

---

## Environment Variables

```env
# App Branding
NEXT_PUBLIC_APP_NAME=Sourdough
NEXT_PUBLIC_APP_SHORT_NAME=SD

# Theme Colors (hex values)
NEXT_PUBLIC_PRIMARY_COLOR=#3b82f6
NEXT_PUBLIC_SECONDARY_COLOR=#6366f1

# Default theme (light or dark)
NEXT_PUBLIC_DEFAULT_THEME=light
```

---

## Related Roadmaps

- [Settings Restructure Roadmap](settings-restructure-roadmap.md) - Branding section in configuration
- [Integration Settings Roadmap](integration-settings-roadmap.md) - Theming options
