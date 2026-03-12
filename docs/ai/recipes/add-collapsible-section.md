# Recipe: Add Collapsible Section

Add collapsible/expandable sections to settings, configuration, or list pages using the shared `CollapsibleCard` component.

## When to Use

- Settings pages with multiple provider/channel sections
- Configuration pages with many options
- Lists where items have expandable details
- Any UI that benefits from "show more/less" behavior

## Prerequisites

Read: [frontend/components/ui/collapsible-card.tsx](../../frontend/components/ui/collapsible-card.tsx)

## Steps

### 1. Import the Component

```tsx
import { CollapsibleCard } from "@/components/ui/collapsible-card";
```

### 2. Basic Usage

Wrap content in `CollapsibleCard` with a title and optional description. Content is collapsed by default.

```tsx
<CollapsibleCard
  title="Provider Name"
  description="Optional short description shown in the header when collapsed."
  defaultOpen={false}
>
  <div className="space-y-4">
    {/* Form fields or other content */}
  </div>
</CollapsibleCard>
```

### 3. With Status Badge

Show configuration or state in the header with a badge.

```tsx
<CollapsibleCard
  title="Slack"
  description="Send notifications to Slack channels."
  status={{
    label: isConfigured ? "Configured" : "Not configured",
    variant: isConfigured ? "success" : "default",
  }}
  defaultOpen={false}
>
  {/* channel config form */}
</CollapsibleCard>
```

**Status variants:** `default`, `success`, `warning`, `destructive`

### 4. With Icon

Add an icon in the header. The icon is shown in a rounded box and adapts when the card is expanded. Use Lucide for generic icons or the shared **ProviderIcon** for provider/channel branding (SSO, LLM, backup, etc.).

```tsx
import { MessageSquare } from "lucide-react";
// Or for provider branding:
import { ProviderIcon } from "@/components/provider-icons";

<CollapsibleCard
  title="Slack"
  description="Send notifications to Slack."
  icon={<MessageSquare className="h-4 w-4" />}
  status={{ label: "Configured", variant: "success" }}
  defaultOpen={false}
>
  {/* content */}
</CollapsibleCard>

// With ProviderIcon (e.g. SSO, LLM, backup config):
<CollapsibleCard
  title="Google"
  icon={<ProviderIcon provider="google" size="sm" style="mono" />}
  ...
/>
```

See [Patterns: ProviderIcon](../patterns/ui-patterns.md#providericon) and [Recipe: Add provider icon](add-provider-icon.md).

### 5. Controlled Mode

Control open state from the parent (e.g. for "expand first item only" or persistence).

```tsx
const [openId, setOpenId] = useState<string | null>("openai");

{providers.map((p) => (
  <CollapsibleCard
    key={p.id}
    title={p.name}
    open={openId === p.id}
    onOpenChange={(open) => setOpenId(open ? p.id : null)}
  >
    {/* content */}
  </CollapsibleCard>
))}
```

### 6. With Header Actions

Put a switch, button, or other control in the header (before the chevron). Use this for toggles that should stay visible when collapsed.

```tsx
<CollapsibleCard
  title="Email"
  description="Send notifications via email."
  status={{ label: configured ? "Configured" : "Not configured", variant: configured ? "success" : "default" }}
  headerActions={
    <Switch
      checked={enabled}
      onCheckedChange={setEnabled}
      onClick={(e) => e.stopPropagation()}
    />
  }
  defaultOpen={false}
>
  {/* form fields */}
</CollapsibleCard>
```

**Tip:** Use `onClick={(e) => e.stopPropagation()}` on interactive header actions so clicking them does not toggle the card.

## Checklist

- [ ] Title is descriptive
- [ ] Status badge shows current state (if applicable)
- [ ] `defaultOpen` set appropriately (usually `false` for lists of many items)
- [ ] Forms inside work correctly when collapsed (no validation/save issues)
- [ ] Keyboard accessible (Tab to focus trigger, Enter/Space to toggle)
- [ ] Header actions that are interactive use `stopPropagation` so they don’t toggle the card

## Key File

**Component:** [frontend/components/ui/collapsible-card.tsx](../../frontend/components/ui/collapsible-card.tsx)

## Related

- [Patterns: CollapsibleCard](../patterns/ui-patterns.md#collapsiblecard) – When to use and props summary
- [Patterns: ProviderIcon](../patterns/ui-patterns.md#providericon) – Icons in card headers
- [Recipe: Add provider icon](add-provider-icon.md) – Adding new icons to the shared set
- [Recipe: Add configuration menu item](add-configuration-menu-item.md) – Adding new config pages that may use collapsible sections

### Implementation Journal

- [Notification Config Split (2026-01-28)](../../journal/2026-01-28-notification-config-split.md)
