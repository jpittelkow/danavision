# ADR 004: Mobile-First Architecture

## Status

Accepted

## Date

2024-12-30

## Context

DanaVision is a shopping list application that users will primarily interact with while:

1. Shopping in stores (mobile)
2. Browsing products on their couch (mobile/tablet)
3. Researching deals at their desk (desktop)

The majority of use cases involve mobile devices, often with camera access for product identification.

## Decision

We will adopt a **Mobile-First Architecture** approach:

### Design Principles

1. **Mobile-first CSS** - Design for mobile viewport first, then enhance for larger screens
2. **Touch-friendly UI** - Large tap targets (minimum 44x44px), appropriate spacing
3. **Camera integration** - First-class support for camera capture on mobile devices
4. **Offline considerations** - Graceful degradation when network is poor

### Technical Implementation

1. **Responsive Layout** - Sidebar collapses to hamburger menu on mobile
2. **Touch Gestures** - Swipe actions where appropriate
3. **Camera Access** - HTML5 capture attribute for direct camera access
4. **Image Upload** - Support both camera capture and gallery selection

### Components

- `ImageUpload` component with camera button on mobile
- Responsive `AppLayout` with mobile sidebar toggle
- Touch-friendly cards and buttons
- Mobile-optimized forms

### Breakpoints

```css
/* Mobile first */
.container { /* mobile styles */ }

/* Tablet */
@media (min-width: 768px) { /* tablet styles */ }

/* Desktop */
@media (min-width: 1024px) { /* desktop styles */ }
```

## Consequences

### Positive

- Better mobile user experience
- Faster mobile page loads (simpler base CSS)
- Camera integration enables Smart Add feature
- Progressive enhancement for larger screens

### Negative

- Desktop-specific features may require extra work
- Testing needed across multiple viewports
- Some complex desktop layouts need rethinking

## Related Decisions

- [ADR 007: Smart Add Feature](007-smart-add-feature.md) - Uses mobile camera integration
