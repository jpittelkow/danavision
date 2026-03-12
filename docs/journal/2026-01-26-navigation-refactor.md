# Navigation Refactor - January 26, 2026

## Overview

Today we implemented a significant UI refactoring to introduce global navigation and restrict settings access to admin users. This was a foundational change that affects the entire authenticated user experience.

## Implementation Approach

### Component Architecture

We took a component-first approach, creating three new components before modifying existing layouts:

1. **Sidebar Component** (`components/sidebar.tsx`)
   - Fixed position, 64px width (w-16)
   - Top section with Home button
   - Separator for visual division
   - Bottom section with Settings button (admin-only)
   - Uses pathname matching for active state indication

2. **Header Component** (`components/header.tsx`)
   - Extracted common header logic
   - Key fix: Used `ml-auto` on right-side container for explicit right-justification
   - Maintains all existing functionality (theme toggle, user info, sign out)

3. **AppShell Component** (`components/app-shell.tsx`)
   - Combines Sidebar + Header + Content wrapper
   - Handles layout spacing (pl-16 for sidebar offset)
   - Single source of truth for authenticated app structure

### Layout Updates

The refactoring touched multiple layout files:

- **Dashboard Layout**: Now wraps children with AppShell instead of just passing through
- **Dashboard Page**: Removed inline header, conditionally shows settings cards only for admins
- **Settings Layout**: Added admin check (redirects non-admins), removed duplicate header
- **Admin Layout**: Removed duplicate header, kept section-specific sidebar

### Design Decisions

**Sidebar Width**: Chose 64px (w-16) as a compromise between functionality and space usage. Icons-only navigation keeps it compact while remaining usable. Could be expanded to show labels on hover in the future.

**Active State**: Used button variant changes (`default` vs `ghost`) combined with background color to indicate active route. This provides clear visual feedback without requiring additional UI elements.

**Right-Justification**: The header fix was subtle but important. Using `ml-auto` explicitly pushes the right-side items to the edge, ensuring consistent alignment. The previous `justify-between` worked but wasn't as explicit about the intent.

**Settings Access**: Made the decision to restrict ALL settings to admin-only. This simplifies the user experience but means we may need to separate user preferences from system settings in the future if needed.

## Challenges Encountered

1. **Layout Spacing**: Initially forgot to add padding-left to account for the fixed sidebar. The AppShell component handles this with `pl-16` on the main content wrapper.

2. **Active State Detection**: Used `pathname?.startsWith("/settings")` for the Settings button to handle all settings sub-routes. The Home button uses exact match since `/dashboard` is the only route it should be active on.

3. **Conditional Rendering**: The Settings button in the sidebar is conditionally rendered based on `user?.is_admin`. This works well with the auth state from Zustand.

## Observations

- **Code Duplication Reduction**: The header was duplicated in 3 places (dashboard, settings, admin). Now it's a single component. This will make future header changes much easier.

- **Consistency**: Having a global sidebar creates a consistent navigation experience. Users always know where to find Home and Settings (if admin).

- **Future Extensibility**: The sidebar structure makes it easy to add more navigation items in the future. The top section could accommodate additional primary navigation, and the bottom section could have more admin-only items.

- **Responsive Considerations**: The current implementation uses fixed positioning which works well on desktop. On mobile, we might want to consider a collapsible/drawer pattern, but that's outside the current scope.

## Trade-offs

**Space Usage**: The fixed sidebar takes 64px of horizontal space on all pages. This is acceptable for the consistency and navigation benefits it provides.

**Settings Access**: Restricting all settings to admin-only simplifies the current implementation but may require future work if user-specific preferences are needed. This is a reasonable trade-off for now.

**Layout Complexity**: The AppShell adds a layer of abstraction, but it centralizes layout logic and makes it easier to maintain.

## Next Steps (Future Considerations)

1. **Collapsible Sidebar**: Could add a toggle to collapse/expand the sidebar to save space
2. **User Preferences**: If user-specific preferences are needed, create a separate "Preferences" or "Account" section
3. **Backend Validation**: Ensure backend API endpoints also validate admin access for settings
4. **Mobile Responsiveness**: Consider drawer/menu pattern for mobile devices
5. **Navigation Breadcrumbs**: Could add breadcrumbs in the header for deeper navigation context

## Testing Notes

- Verify sidebar appears on all authenticated pages
- Verify Settings button only shows for admin users
- Verify header items are right-justified properly
- Verify settings pages redirect non-admin users
- Verify dashboard only shows settings cards for admins
- Verify active states work correctly for Home and Settings buttons

## Code Quality

The implementation follows existing patterns:
- Uses shadcn/ui components (Button, Separator, Badge)
- Follows Tailwind utility-first styling
- Uses TypeScript for type safety
- Leverages Next.js App Router patterns
- Maintains consistency with existing design system

Overall, this refactoring improves code organization, reduces duplication, and provides a better user experience with consistent global navigation.
