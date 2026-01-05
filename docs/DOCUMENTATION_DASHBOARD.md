# Dashboard Documentation

This document describes the DanaVision dashboard features and their implementation.

## Overview

The dashboard provides a comprehensive overview of your price tracking activity, including statistics, charts, and actionable insights.

## Features

### Quick Stats Cards

Four clickable stat cards at the top of the dashboard:

| Card | Description | Links To |
|------|-------------|----------|
| Shopping Lists | Total number of lists (owned + shared) | `/lists` |
| Total Items | Count of all tracked items | `/items` |
| Price Drops | Items with recent price decreases | `/items?status=drops` |
| Potential Savings | Sum of all price drop amounts | - |

### Active Jobs Widget

Shows currently running AI jobs with:
- Job type label (e.g., "Firecrawl Price Discovery")
- Progress percentage and bar
- Up to 5 most recent active jobs

When no jobs are running, displays a "No active jobs" message with a checkmark.

### Price Check Status

Four metric tiles showing:

| Metric | Description |
|--------|-------------|
| Last Updated | Relative time since any item was price-checked |
| All-Time Lows | Count of items currently at their lowest tracked price |
| Below Target | Items currently priced at or below target price |
| Need Refresh | Items not checked in 7+ days |

### 7-Day Price Activity Chart

An area chart showing average price trends over the past 7 days. Only displays when price history data exists.

**Data Points:**
- Date (X-axis)
- Average price across all items (Y-axis)

**Interactivity:**
- Hover to see exact values
- Responsive sizing

### Recent Price Drops

Lists the 5 most recent items with price drops, showing:
- Product image
- Product name (links to item detail)
- Current and previous prices
- Percentage discount badge
- List name

### All-Time Lows

Lists up to 5 items currently at their all-time lowest price, showing:
- Product image  
- Product name (links to item detail)
- Current price
- List name

Items at all-time low are highlighted with an amber border.

### Best Value Stores

A horizontal bar chart ranking stores by how often they have the "best price" for tracked items.

**Calculation:**
- For each item with multiple vendor prices, determine which vendor has the lowest current in-stock price
- Count wins per vendor
- Display top 6 stores

**Tooltip shows:**
- Store name
- Number of "best price" wins
- Total savings vs highest-priced vendor

### Items Needing Attention

Lists items not checked in 7+ days, showing:
- Product image
- Product name (links to item detail)
- Time since last check

Helps users identify stale price data that should be refreshed.

### Empty State

When no items exist, displays a welcome message with calls-to-action:
- "Create Your First List" button
- "Quick Add with AI" button

## API Response

The dashboard endpoint (`GET /dashboard`) returns:

```typescript
interface DashboardResponse {
  stats: {
    lists_count: number;
    items_count: number;
    items_with_drops: number;
    total_potential_savings: number;
    all_time_lows_count: number;
    items_below_target: number;
  };
  recent_drops: DashboardItem[];
  all_time_lows: DashboardItem[];
  store_stats: StoreStats[];
  active_jobs_count: number;
  active_jobs: ActiveJob[];
  last_price_update: string | null;
  price_trend: PriceTrendPoint[];
  items_needing_attention: DashboardItem[];
}
```

## Performance Considerations

1. **Query Optimization**: All queries filter by user's list IDs first
2. **Result Limits**: Lists limited to 5-6 items maximum
3. **Eager Loading**: Relationships loaded efficiently with `->with()`
4. **Chart Data**: Only 7 days of trend data, aggregated by day

## Related Documentation

- [Items Page Documentation](DOCUMENTATION_ITEMS.md)
- [ADR 015: Dashboard Redesign](adr/015-dashboard-redesign.md)
