# ADR 015: Dashboard Redesign and Navigation Updates

## Status

Accepted

## Date

2026-01-05

## Context

The original DanaVision dashboard provided basic statistics (lists count, items count, price drops, potential savings) and simple lists of recent price drops and all-time lows. Users needed more comprehensive analytics to understand their price tracking effectiveness and make informed shopping decisions.

Additionally, the navigation structure included a "Search" function that didn't align with the application's primary workflow, and users couldn't easily view all their items across lists in a single view.

### Problems Identified

1. **Limited Dashboard Insights**: Users couldn't see which stores consistently offered the best prices, how many active AI jobs were running, when prices were last updated, or which items needed attention.

2. **No Unified Items View**: Users had to navigate into each list individually to see their items, making it difficult to get an overview of all tracked products.

3. **Navigation Inconsistency**: The "Search" menu item was redundant since search functionality is primarily used within the Smart Add flow, not as a standalone feature.

4. **Settings Placement**: Settings was grouped with primary navigation items, when it's actually a utility function that should be separate.

## Decision

### Navigation Restructure

We restructured the navigation to:

1. **Remove Search** from main navigation (still accessible via Smart Add)
2. **Add Items** as a new menu item to show all items across all lists
3. **Move Settings** to a separate section above the user profile, with a visual divider

New navigation structure:
```
Main Nav:     Smart Add | Dashboard | Lists | Items
Bottom:       Settings
              User Profile + Sign Out
```

### Dashboard Enhancement

We enhanced the dashboard to provide comprehensive analytics:

1. **Stats Cards**: Quick overview with clickable navigation
   - Shopping Lists count → Links to /lists
   - Total Items count → Links to /items
   - Price Drops count → Links to /items?status=drops
   - Potential Savings amount

2. **Active Jobs Widget**: Shows running AI jobs with progress bars

3. **Price Check Status Card**: 
   - Last price update timestamp
   - All-time lows count
   - Items below target count
   - Items needing refresh count

4. **7-Day Price Activity Chart**: Area chart showing price trends using Recharts

5. **Recent Price Drops**: Items with recent price reductions, linked to item detail

6. **All-Time Lows**: Items at their lowest tracked price, linked to item detail

7. **Store Leaderboard**: Bar chart showing which stores most frequently have the best prices

8. **Items Needing Attention**: Items not checked in 7+ days

### Items Index Page

Created a new `/items` route that:

1. Shows all items from owned and shared lists
2. Supports filtering by:
   - List
   - Price status (drops, all-time lows, below target)
   - Priority (high, medium, low)
   - Purchased status
3. Supports sorting by name, price, updated date, created date
4. Paginated at 50 items per page
5. Card-based grid layout with images, prices, and status badges

## Consequences

### Positive

- **Better Insights**: Users can now see comprehensive analytics about their price tracking
- **Improved Discoverability**: All items are accessible from a single page
- **Cleaner Navigation**: Removal of redundant search link simplifies the UI
- **Better Information Architecture**: Settings separated from primary navigation
- **Actionable Data**: Store leaderboard helps users know where to shop
- **Proactive Alerts**: Items needing attention helps maintain tracking freshness

### Negative

- **Increased Query Load**: Dashboard now runs more queries to gather all analytics
- **Larger Page Size**: Dashboard component is now ~27KB gzipped (was smaller)
- **Learning Curve**: Users familiar with old navigation need to adjust

### Mitigations

- Dashboard queries are optimized with proper indexes and limits
- Charts only render when data exists
- Pagination prevents loading too many items at once

## Technical Implementation

### Backend Changes

1. **DashboardController.php**: Enhanced `index()` method with:
   - Store leaderboard calculation
   - Active jobs query
   - Price trend data aggregation
   - Items needing attention query
   - Helper methods for code reuse

2. **ListItemController.php**: Added `index()` method for items page with:
   - User-scoped queries (owned + shared lists)
   - Filter and sort support
   - Pagination

3. **Routes**: Added `/items` route

### Frontend Changes

1. **AppLayout.tsx**: Restructured navigation
2. **Dashboard.tsx**: Complete redesign with Recharts integration
3. **Items/Index.tsx**: New page with filtering UI
4. **types/index.ts**: New TypeScript interfaces for dashboard data

### Data Flow

```
User Request → DashboardController
                    ↓
              Get User's List IDs (owned + shared)
                    ↓
              Parallel Queries:
              - Recent drops
              - All-time lows
              - Store stats
              - Active jobs
              - Price trend
              - Items needing attention
                    ↓
              Format & Return to Inertia
                    ↓
              Dashboard.tsx renders with Recharts
```

## Alternatives Considered

1. **Keep Search in Navigation**: Rejected because search is primarily a sub-feature of Smart Add, not a primary navigation destination.

2. **Tabs Instead of Separate Items Page**: Rejected because items across all lists is a distinct use case from viewing a single list.

3. **Server-Side Charts**: Rejected in favor of client-side Recharts for interactivity and consistent styling with existing components.

4. **Real-time Dashboard Updates**: Deferred to future enhancement; current polling via JobNotifications component is sufficient.

## References

- [ADR 004: Mobile First Architecture](004-mobile-first-architecture.md)
- [ADR 009: AI Job System](009-ai-job-system.md)
- [Dashboard Documentation](../DOCUMENTATION_DASHBOARD.md)
- [Items Page Documentation](../DOCUMENTATION_ITEMS.md)
- Recharts documentation: https://recharts.org/
