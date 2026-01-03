# ADR 005: User-Based Lists with Sharing

## Status
Accepted

## Date
2025-01-01

## Context
DanaVision needs to support:
- Personal shopping lists owned by individual users
- Sharing lists with family members or friends
- Different permission levels for shared access
- Easy collaboration without complex household management

## Decision
We will implement a user-owned list model with explicit sharing:

### Ownership
- Each `ShoppingList` belongs to exactly one `User` (owner)
- Only owners can delete lists
- Owners have full control over sharing

### Sharing Model
The `ListShare` model tracks shared access:
- `shopping_list_id` - The shared list
- `user_id` - User being shared with
- `shared_by_user_id` - User who created the share
- `permission` - Access level: `view`, `edit`, or `admin`
- `accepted_at` - Null until accepted (pending invitation)

### Permission Levels
1. **view** - Read-only access to list and items
2. **edit** - Can add/update/delete items
3. **admin** - Can edit + share with others

### Authorization Flow
1. Share is created (pending)
2. Target user sees invitation
3. User accepts or declines
4. If accepted, they can access per permission level

## Consequences

### Positive
- Simple mental model (owner + shares)
- No complex household management
- Granular permissions
- Clear audit trail of who shared what

### Negative
- No automatic family grouping
- Each share is explicit (some overhead)
- Owner is single point of control

## Related Decisions
- [ADR-006: Email Notifications](006-email-notifications.md)
