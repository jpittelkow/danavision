# Header Theme Toggle - 2026-02-06

## Overview

Added a compact light/dark/system theme toggle to the header, positioned to the left of the user dropdown. Users can quickly switch themes without visiting Preferences.

## Implementation

### 1. ThemeToggle component rewrite

- Rewrote `frontend/components/theme-toggle.tsx` from a dropdown to a compact inline icon group (Sun, Moon, Monitor)
- Three ghost buttons with `h-8 w-8` sizing, active mode highlighted with `bg-muted`
- Each button has a tooltip (Light, Dark, System) and syncs to the API via `api.put("/user/settings", { theme })`
- Uses existing `useTheme()` hook; stays in sync with Preferences page and onboarding wizard

### 2. Header integration

- Imported `ThemeToggle` in `frontend/components/header.tsx`
- Placed between `NotificationBell` and `UserDropdown` in the right-side header controls

## Files Modified

- `frontend/components/theme-toggle.tsx` — rewritten as compact 3-icon inline group
- `frontend/components/header.tsx` — added ThemeToggle between NotificationBell and UserDropdown

## Notes

- Preferences page and onboarding wizard retain their theme pickers; all use the same theme provider and stay in sync
- Previously the theme toggle was removed from the header (per branding roadmap); this reintroduces quick-access with a compact design
