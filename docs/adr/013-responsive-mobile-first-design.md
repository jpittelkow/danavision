# ADR-013: Responsive Mobile-First Design

## Status

Accepted

## Date

2026-01-27

## Context

The application needs to work well on all device sizes, from mobile phones to large desktop monitors. Users expect a seamless experience regardless of their device, and mobile usage continues to grow across all user demographics.

Key considerations:
- Mobile-first ensures base functionality works on constrained devices
- Tailwind CSS provides a mobile-first breakpoint system by default
- The sidebar navigation pattern needs adaptation for mobile
- Tables and data-heavy pages require special handling
- Touch interactions differ from mouse interactions

## Decision

We adopt a **mobile-first responsive design** approach using Tailwind CSS breakpoints and React-based conditional rendering where necessary.

### Core Principles

1. **Mobile-First CSS**
   - Base styles target mobile viewports (no breakpoint prefix)
   - Add styles for larger screens using `sm:`, `md:`, `lg:`, `xl:`, `2xl:` prefixes
   - Example: `className="flex flex-col md:flex-row"`

2. **Breakpoint Strategy**
   - Use Tailwind's default breakpoints (640px, 768px, 1024px, 1280px, 1536px)
   - Primary breakpoint is `md` (768px) for mobile ↔ tablet/desktop transition
   - The `useIsMobile` hook uses 768px as the threshold

3. **Touch-First Interactions**
   - Minimum 44x44px touch targets for all interactive elements
   - Adequate spacing between clickable items
   - Hover states should have touch equivalents (active states)

4. **Layout Patterns**
   - **Sidebar**: Desktop shows fixed sidebar; mobile uses Sheet (drawer) component
   - **Tables**: Wrap in `overflow-x-auto` container; consider card view alternative
   - **Forms**: Full-width inputs on mobile; multi-column on larger screens
   - **Grids**: Single column on mobile; multi-column on larger screens

5. **Conditional Rendering**
   - Use `useIsMobile` hook for components that need fundamentally different mobile UIs
   - Prefer CSS-only solutions when possible (hidden/shown with breakpoints)
   - Avoid duplicating content for different screen sizes

### Breakpoint Reference

| Breakpoint | Min Width | Use Case |
|------------|-----------|----------|
| (default)  | 0px       | Mobile phones, base styles |
| `sm`       | 640px     | Large phones in landscape |
| `md`       | 768px     | Tablets, primary mobile breakpoint |
| `lg`       | 1024px    | Small laptops, notebooks |
| `xl`       | 1280px    | Desktops |
| `2xl`      | 1536px    | Large monitors |

### Mobile Navigation Pattern

```
Desktop (≥768px):
┌────────────┬─────────────────────────────┐
│  Sidebar   │        Content Area         │
│  (fixed)   │                             │
└────────────┴─────────────────────────────┘

Mobile (<768px):
┌─────────────────────────────────────────┐
│  Header with Hamburger                   │
├─────────────────────────────────────────┤
│                                          │
│           Content Area                   │
│           (full width)                   │
│                                          │
└─────────────────────────────────────────┘

[Drawer slides in from left when hamburger clicked]
```

## Consequences

### Positive

- Consistent experience across all device sizes
- Mobile users get optimized UI, not just shrunk desktop
- Tailwind's mobile-first approach aligns with industry best practices
- Single codebase serves all devices
- Progressive enhancement ensures core functionality everywhere

### Negative

- Additional testing required across viewport sizes
- Some components need two implementations (desktop/mobile)
- Developers must think mobile-first, which may require mindset shift
- Complex components (tables, charts) need extra responsive handling

### Implementation Notes

- The `useIsMobile` hook in `frontend/lib/use-mobile.ts` detects viewport width
- Sidebar uses Sheet component for mobile drawer behavior
- All new components must be built mobile-first
- Existing components should be audited per the roadmap

## Key Implementation Files

- `frontend/lib/use-mobile.ts` - Mobile detection hook
- `frontend/components/sidebar.tsx` - Responsive sidebar implementation
- `frontend/components/app-shell.tsx` - Main layout with responsive structure
- `frontend/components/header.tsx` - Header with mobile menu toggle
- `frontend/components/ui/sheet.tsx` - Drawer component for mobile navigation
- `frontend/tailwind.config.ts` - Breakpoint configuration
- `frontend/app/globals.css` - Global responsive styles

## Related

- [Mobile Responsiveness Roadmap](../plans/mobile-responsive-roadmap.md)
- [Responsive Design Pattern](../ai/patterns/responsive.md)
- [Recipe: Make Component Responsive](../ai/recipes/make-component-responsive.md)

## Implementation Journal

- [Mobile Responsiveness Implementation (2026-01-27)](../journal/2026-01-27-mobile-responsiveness-implementation.md)
