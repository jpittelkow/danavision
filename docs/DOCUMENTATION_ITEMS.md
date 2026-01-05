# Items Page Documentation

This document describes the All Items page (`/items`) feature and its implementation.

## Overview

The Items page provides a unified view of all items across all shopping lists, with powerful filtering and sorting capabilities.

## Features

### Item Grid

Displays items in a responsive card grid:
- 1 column on mobile
- 2 columns on medium screens
- 3 columns on large screens
- 4 columns on extra-large screens

### Item Cards

Each item card displays:

| Element | Description |
|---------|-------------|
| Product Image | Square thumbnail, or Package icon if no image |
| All-Time Low Badge | Amber badge when item is at lowest tracked price |
| Price Drop Badge | Green badge with percentage when price has dropped |
| Purchased Badge | Shows if item has been marked as purchased |
| Product Name | Truncated to 2 lines, links to item detail |
| Current Price | Highlighted in green/amber for drops/lows |
| Previous Price | Strikethrough if different from current |
| List Name | Shows which list the item belongs to |
| Last Checked | Relative time since last price check |
| Priority Badge | Shows "High priority" or "Medium priority" badges |

### Filters

Click the "Filters" button to reveal filter options:

#### List Filter
- Dropdown of all owned and shared lists
- Shows item count per list
- "All Lists" to show everything

#### Price Status Filter
- **Any Status**: No filter
- **Price Drops**: Items where current_price < previous_price
- **All-Time Lows**: Items where current_price <= lowest_price
- **Below Target**: Items where current_price <= target_price

#### Priority Filter
- **Any Priority**: No filter
- **High**: High priority items only
- **Medium**: Medium priority items only
- **Low**: Low priority items only

#### Status Filter
- **All Items**: No filter
- **Not Purchased**: Items not yet purchased
- **Purchased**: Items marked as purchased

#### Sort By
- **Recently Updated**: Most recently modified first (default)
- **Oldest Updated**: Least recently modified first
- **Name (A-Z)**: Alphabetical ascending
- **Name (Z-A)**: Alphabetical descending
- **Price (Low-High)**: Cheapest first
- **Price (High-Low)**: Most expensive first

### Filter Badge

When filters are active, the Filters button shows an "Active" badge.

### Pagination

Items are paginated at 50 per page. Navigation controls appear when there are multiple pages.

### Empty State

When no items match the current filters:
- Shows Package icon
- "No items found" message
- Suggests adjusting filters or adding items
- "Go to Lists" button

## API Endpoint

### Request

```
GET /items
```

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `list_id` | integer | Filter to specific list |
| `status` | string | One of: `drops`, `all_time_lows`, `below_target` |
| `priority` | string | One of: `high`, `medium`, `low` |
| `purchased` | string | `0` for not purchased, `1` for purchased |
| `sort` | string | One of: `product_name`, `current_price`, `updated_at`, `created_at`, `priority` |
| `dir` | string | `asc` or `desc` (default: `desc`) |
| `page` | integer | Page number for pagination |

### Response

```typescript
interface ItemsResponse {
  items: {
    data: ItemWithList[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
  };
  lists: ListOption[];
  filters: {
    list_id?: string;
    status?: string;
    priority?: string;
    purchased?: string;
    sort: string;
    dir: string;
  };
}

interface ItemWithList {
  id: number;
  product_name: string;
  product_image_url?: string;
  current_price?: number;
  previous_price?: number;
  lowest_price?: number;
  target_price?: number;
  priority: 'low' | 'medium' | 'high';
  is_purchased: boolean;
  is_at_all_time_low?: boolean;
  last_checked_at?: string;
  list: {
    id: number;
    name: string;
  };
}
```

## Security

- Items are scoped to user's owned and shared lists only
- Users cannot see other users' items
- List filter validates list_id belongs to user

## Usage Examples

### View All Price Drops

```
GET /items?status=drops
```

### View High Priority Items Not Yet Purchased

```
GET /items?priority=high&purchased=0
```

### View Items from Specific List, Sorted by Price

```
GET /items?list_id=5&sort=current_price&dir=asc
```

## Related Documentation

- [Dashboard Documentation](DOCUMENTATION_DASHBOARD.md)
- [ADR 015: Dashboard Redesign](adr/015-dashboard-redesign.md)
