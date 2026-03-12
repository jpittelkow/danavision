# Configuration Navigation Redesign

**Priority:** MEDIUM  
**Status:** Complete (2026-01-29)

## Problem Statement

The configuration section currently has 13 flat navigation items, which creates a long, unorganized list that may be overwhelming for administrators. As features continue to be added, this problem will worsen.

### Current Navigation Items (13)

1. System
2. Users
3. Notifications
4. AI / LLM
5. Email
6. Email Templates
7. SSO
8. Storage
9. API & Webhooks
10. Theme & Branding
11. Audit Log
12. Jobs
13. Backup & Restore

## Goals

- Improve discoverability of configuration options
- Group related settings logically
- Reduce cognitive load for administrators
- Maintain quick access to frequently used settings
- Support future feature additions without navigation bloat

## Proposed Approaches

### Option A: Grouped Navigation with Collapsible Sections

Group related items into collapsible sections:

```
📋 General
  ├── System
  └── Theme & Branding

👥 Users & Access
  ├── Users
  ├── SSO
  └── API & Webhooks

📬 Communications
  ├── Email
  ├── Email Templates
  └── Notifications

🤖 Integrations
  ├── AI / LLM
  └── Storage

📊 Monitoring
  ├── Audit Log
  └── Jobs

💾 Data
  └── Backup & Restore
```

**Pros:**
- Clear logical groupings
- Expandable/collapsible reduces visual clutter
- Easy to add new items to appropriate groups

**Cons:**
- Requires extra click to expand sections
- May hide items users are looking for

### Option B: Tab-Based Top Navigation + Sidebar

Use tabs for major categories with sub-navigation:

```
[General] [Users & Security] [Communications] [Data & Monitoring]
         ↓
    Sidebar shows items for selected tab
```

**Pros:**
- Clear visual separation of concerns
- Familiar pattern (similar to AWS Console, Stripe Dashboard)

**Cons:**
- Two-level navigation may feel complex
- Requires understanding category structure

### Option C: Consolidate Pages

Merge related functionality into fewer, more comprehensive pages:

- **Communications:** Combine Email + Email Templates + Notifications
- **Security:** Combine Users + SSO + API & Webhooks
- **Monitoring:** Combine Audit Log + Jobs

**Pros:**
- Fewer navigation items
- Related settings in one place

**Cons:**
- Pages become longer/more complex
- May require sub-tabs within pages

### Option D: Quick Access + Full List

Show top 4-5 frequently used items, with "More settings" expanding full list:

```
⭐ Quick Access
  System | Users | Email | Backup

📋 All Settings
  [expandable full list]
```

**Pros:**
- Quick access to common items
- Full list still available

**Cons:**
- Requires tracking/configuring "frequently used"
- Two sections to maintain

## Recommended Approach

**Option A (Grouped Navigation)** is recommended as the starting point:
- Most intuitive for users
- Aligns with common admin dashboard patterns
- Doesn't require consolidating existing pages
- Easy to implement incrementally

## Implementation Phases

### Phase 1: Design & Planning
- [x] Analyze usage patterns (if analytics available)
- [x] Finalize groupings based on user feedback
- [x] Create UI mockups for grouped navigation
- [x] Decide on expand/collapse default states

### Phase 2: Implementation
- [x] Refactor navigation data structure to support groups
- [x] Update desktop sidebar with collapsible groups
- [x] Update mobile drawer with same structure
- [x] Add persistence for expanded/collapsed state (localStorage)

### Phase 3: Polish
- [x] Add smooth expand/collapse animations
- [x] Ensure keyboard navigation works within groups
- [x] Test with screen readers for accessibility
- [x] Update documentation

## Technical Considerations

- Navigation structure defined in `frontend/app/(dashboard)/configuration/layout.tsx`
- Consider using `Collapsible` component from shadcn/ui
- State persistence in localStorage for user preference
- Mobile drawer should mirror desktop behavior

## Success Criteria

- [x] All configuration items remain accessible
- [x] Navigation feels less overwhelming
- [x] New items can be added without degrading UX
- [x] Works well on mobile and desktop
- [x] Maintains accessibility standards
