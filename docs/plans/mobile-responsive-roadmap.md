# Mobile Responsiveness Roadmap

Make the application fully responsive and optimized for mobile devices.

**Priority**: HIGH  
**Status**: Complete  
**Last Updated**: 2026-01-27

**Dependencies**:
- [Branding & UI Consistency](branding-ui-consistency-roadmap.md) - Header and sidebar refactor provides foundation

---

## Task Checklist

### Audit & Assessment (Optional QA)
- [ ] Audit current responsive breakpoints and identify gaps
- [ ] Test all pages on mobile viewport sizes (320px, 375px, 414px)
- [ ] Test all pages on tablet viewport sizes (768px, 1024px)
- [ ] Document components that need responsive updates
- [ ] Identify touch-specific interaction issues

### Navigation & Layout (HIGH Priority)
- [x] Implement mobile-friendly sidebar (slide-out drawer or bottom nav)
- [x] Add hamburger menu toggle for mobile
- [x] Ensure header works on small screens
- [x] Handle sidebar collapse/expand on mobile appropriately
- [ ] Add swipe gestures for navigation (optional, future)
- [ ] Ensure breadcrumbs work on mobile or are hidden appropriately (optional QA)

### Dashboard & Content Pages (HIGH Priority)
- [x] Make dashboard widgets stack vertically on mobile
- [ ] Ensure charts and data visualizations are responsive (optional QA)
- [x] Add horizontal scrolling for wide tables (or card view alternative)
- [ ] Ensure all forms are usable on mobile (optional QA)
- [x] Test and fix any overflow issues

### Settings & Configuration Pages (Optional QA)
- [ ] Make settings forms mobile-friendly
- [ ] Ensure color pickers work with touch input
- [ ] Test file upload functionality on mobile
- [x] Make tabs/navigation work on small screens
- [x] Ensure all buttons and actions are tappable (min 44px touch target)

### Authentication Pages (Optional QA)
- [ ] Verify login/register pages work on mobile
- [ ] Ensure password fields have proper mobile keyboard types
- [ ] Test 2FA input on mobile devices
- [ ] Make error messages readable on small screens

### Touch & Interaction (Optional QA)
- [x] Ensure all clickable elements have minimum 44px touch targets
- [ ] Add appropriate touch feedback (active states)
- [ ] Test dropdown menus on touch devices
- [ ] Ensure modals/dialogs are mobile-friendly
- [ ] Test date pickers and other complex inputs on mobile

### Performance (Optional QA, LOW Priority)
- [ ] Optimize images for mobile (responsive images, srcset)
- [ ] Reduce unnecessary JavaScript on mobile
- [ ] Test and optimize for slow mobile connections
- [ ] Implement lazy loading where appropriate

### Testing & QA (Optional QA, Not Blocking)
- [ ] Test on real iOS device (Safari)
- [ ] Test on real Android device (Chrome)
- [ ] Test landscape orientation
- [ ] Test with various font size/accessibility settings
- [ ] Add responsive tests to e2e test suite

---

## Current State

**Implementation complete (2026-01-27)**:
- Mobile sidebar as Sheet drawer (below `md` breakpoint); hamburger in header; overlay/backdrop; close on navigation or outside click
- App shell responsive padding (`pl-0` on mobile, `md:pl-16` / `md:pl-56` on desktop)
- Horizontal scroll wrappers on tables (API tokens, jobs, audit, users)
- 44px min touch targets on sidebar nav, header hamburger, config nav links, user table actions
- Dashboard and config layouts use responsive grids; tabs and nav work on small screens

**Optional QA (not blocking)**:
- Real-device testing (iOS Safari, Android Chrome), viewport audit, e2e responsive tests, performance optimizations

**Key Files**:
- `frontend/components/sidebar.tsx` - Sidebar component
- `frontend/components/app-shell.tsx` - Main layout shell
- `frontend/components/header.tsx` - Header component
- `frontend/app/globals.css` - Global responsive styles
- `frontend/tailwind.config.ts` - Tailwind breakpoint configuration

---

## Implementation Plan

### Phase 1: Mobile Navigation

1. **Mobile sidebar implementation**
   - Convert sidebar to slide-out drawer on mobile
   - Add hamburger menu button in header
   - Implement overlay/backdrop when sidebar is open
   - Add close on navigation or outside click

2. **Header adjustments**
   - Stack or hide secondary header elements on mobile
   - Ensure logo and primary actions remain visible
   - Adjust spacing for mobile

### Phase 2: Content Responsiveness

1. **Dashboard updates**
   - Use CSS Grid or Flexbox with responsive rules
   - Stack widgets vertically on mobile (single column)
   - Ensure all content is readable without horizontal scroll

2. **Tables**
   - Implement horizontal scroll wrapper for wide tables
   - Consider card view alternative for data-heavy tables
   - Ensure table headers remain visible (sticky if scrolling)

3. **Forms**
   - Full-width inputs on mobile
   - Appropriate spacing between form elements
   - Clear labels and error messages

### Phase 3: Polish & Testing

1. **Touch optimizations**
   - Audit and fix touch targets < 44px
   - Add hover/active states that work on touch
   - Test all interactive elements

2. **Cross-device testing**
   - Test on multiple real devices
   - Use browser dev tools for edge cases
   - Document and fix issues found

---

## Responsive Breakpoints

Using Tailwind CSS default breakpoints:

| Breakpoint | Min Width | Typical Devices |
|------------|-----------|-----------------|
| `sm` | 640px | Large phones (landscape) |
| `md` | 768px | Tablets |
| `lg` | 1024px | Small laptops |
| `xl` | 1280px | Desktops |
| `2xl` | 1536px | Large desktops |

**Mobile-first approach**: Default styles for mobile, then add breakpoint modifiers for larger screens.

---

## Files to Create/Modify

**New Files**:
- `frontend/lib/use-mobile.ts` - Hook to detect mobile viewport (`md` breakpoint)
- `frontend/components/ui/sheet.tsx` - Added via shadcn (mobile drawer)

**Modified Files**:
- `frontend/components/sidebar.tsx` - Add mobile drawer behavior
- `frontend/components/app-shell.tsx` - Adjust layout for mobile
- `frontend/components/header.tsx` - Add mobile menu toggle
- `frontend/app/globals.css` - Mobile-specific styles
- Various page components - Responsive layout adjustments

---

## Related Roadmaps

- [Branding & UI Consistency Roadmap](branding-ui-consistency-roadmap.md) - Header/sidebar foundation
