# Recipe: Add Configuration Menu Item

Step-by-step guide to add a new item to the configuration navigation (grouped, collapsible sidebar and mobile drawer).

## When to Use

- Adding a new configuration area that should appear in the Configuration section
- The navigation uses **groups**; new items are added to an existing group or (rarely) a new group

## Configuration Groups

| Group | Use for |
|-------|--------|
| General | System-wide settings, theme and branding |
| Users & Access | Users, SSO, API and webhooks |
| Communications | Notifications, email, email templates |
| Integrations | AI/LLM, storage, external services |
| Logs & Monitoring | Audit log, application logs, access logs, log retention, jobs |
| Data | Backup and restore |

## Steps

### 1. Choose the Right Group

Pick the group that best fits the new feature. If none fit, consider whether the feature belongs in configuration or elsewhere. Creating a **new group** is rare and should be justified (e.g. a major new area like "Security" or "Compliance").

### 2. Add the Navigation Item

Edit [frontend/app/(dashboard)/configuration/layout.tsx](frontend/app/(dashboard)/configuration/layout.tsx).

Find the `navigationGroups` array and the group you chose. Add a new item to that group's `items` array:

```tsx
{
  name: "Your Feature",
  href: "/configuration/your-feature",
  icon: YourIcon, // from lucide-react
  description: "Short description for the nav",
},
```

- **name**: Label shown in the sidebar
- **href**: Must match the route path (e.g. `/configuration/your-feature`)
- **icon**: Lucide icon component (import at top of layout.tsx if new)
- **description**: One line shown under the name in the nav

Example: adding "Integrations" to the Integrations group:

```tsx
{
  name: "Integrations",
  icon: Brain,
  items: [
    { name: "AI / LLM", href: "/configuration/ai", icon: Brain, description: "..." },
    { name: "Storage", href: "/configuration/storage", icon: HardDrive, description: "..." },
    { name: "New Service", href: "/configuration/new-service", icon: Zap, description: "Configure external service" },
  ],
},
```

### 3. Create the Configuration Page

Create the page so the href resolves:

- **Path**: `frontend/app/(dashboard)/configuration/[slug]/page.tsx`
- **Example**: For href `/configuration/new-service`, create `frontend/app/(dashboard)/configuration/new-service/page.tsx`

For form-based config pages, follow [Recipe: Add Configuration Page](add-config-page.md) (Zod schema, react-hook-form, optional-by-default, etc.).

### 4. Backend Routes (If Needed)

If the page loads or saves settings:

- Add API routes in `backend/routes/api.php`
- Create or extend a controller (e.g. `backend/app/Http/Controllers/Api/`)
- Use `SettingService` when config is stored in the database with env fallback (see add-config-page.md)

### 5. Update Documentation

- **features.md**: Add the new configuration area and what it does
- Optionally add or update an ADR if this introduces a new pattern

## Verification

- [ ] New item appears in the correct group in the configuration sidebar (desktop)
- [ ] New item appears in the same group in the mobile drawer
- [ ] Clicking the item navigates to the new page
- [ ] The group containing the current page is expanded by default
- [ ] Expanded/collapsed state persists after refresh (localStorage)

## Key Files

- `frontend/app/(dashboard)/configuration/layout.tsx` – `navigationGroups` and `GroupedNavigation`
- [Configuration navigation pattern](../patterns/ui-patterns.md#configuration-navigation)
- [Feature list](../../features.md) to update

## Adding a New Group (Rare)

Only add a new group when the feature area does not fit existing groups and is substantial enough to warrant its own section.

1. In `layout.tsx`, add a new object to `navigationGroups` with `name`, `icon`, and `items` (array of at least one item).
2. Use a unique, descriptive group name and a Lucide icon.
3. Document the new group in this recipe’s "Configuration Groups" table and in [Configuration Navigation pattern](../patterns/ui-patterns.md#configuration-navigation).

## Related

- [Recipe: Add Configuration Page](add-config-page.md) – Building the config page and form
- [Patterns: Configuration Navigation](../patterns/ui-patterns.md#configuration-navigation)
- [Context loading: Settings/Configuration Work](../context-loading.md#settingsconfiguration-work)

### Implementation Journal

- [Config Nav Redesign (2026-01-29)](../../journal/2026-01-29-config-nav-redesign.md)
- [Navigation Refactor (2026-01-26)](../../journal/2026-01-26-navigation-refactor.md)
