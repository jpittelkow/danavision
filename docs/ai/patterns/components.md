# Global Component Patterns

Never duplicate logic across pages. All reusable functionality must be shared components or utilities.

## Component Locations

| Type | Location | When to Create |
|------|----------|----------------|
| UI Components | `frontend/components/ui/` | Generic UI elements (buttons, cards, inputs) |
| Feature Components | `frontend/components/` | App-specific components used on 2+ pages |
| Hooks | `frontend/lib/` or `frontend/hooks/` | Shared stateful logic |
| Utilities | `frontend/lib/` | Pure functions, API client, helpers |

**Before creating any component or utility:**
1. Search the codebase for existing implementations
2. If similar functionality exists, use or extend it
3. Only create page-specific code when explicitly required and document why

## Key Global Components

- `Logo` - Used on auth pages, sidebar, dashboard
- `usePageTitle` - Sets document title consistently (format: "Page Name | App Name")
- `api` - API client with auth handling
- `formatDate`, `formatDateTime`, `formatTimestamp`, `formatBytes`, `getErrorMessage` - in `frontend/lib/utils.ts`

## Reusable Component Pattern

Components with multiple variants, sizes, and fallback behavior:

```tsx
export interface ExampleComponentProps {
  variant?: 'full' | 'compact' | 'minimal';
  size?: 'sm' | 'md' | 'lg';
  src?: string | null;
  label: string;
  className?: string;
}

const sizeConfig = {
  sm: { container: 'h-6 w-6', text: 'text-sm', gap: 'gap-1.5' },
  md: { container: 'h-8 w-8', text: 'text-base', gap: 'gap-2' },
  lg: { container: 'h-10 w-10', text: 'text-lg', gap: 'gap-2.5' },
};

export function ExampleComponent({ variant = 'full', size = 'md', src, label, className }: ExampleComponentProps) {
  const [imageError, setImageError] = useState(false);
  const sizes = sizeConfig[size];
  // ... variant handling, image fallback
}
```

Key principles: TypeScript interface with JSDoc, size config object, `cn()` for conditional classes, image `onError` fallback, sensible defaults.

**Real example:** `frontend/components/logo.tsx`

## Charts (shadcn + Recharts)

Use ChartContainer, ChartTooltip, ChartTooltipContent with Recharts. Config-driven colors and tooltips; theme-aware.

- **ChartContainer**: Wrap charts; requires `config` (ChartConfig) and `className` with `min-h-[...]`
- **ChartConfig**: Keys map to data keys; each entry has `label` and `color`
- Prefer `AreaChart`, `BarChart`, `PieChart` from `recharts`. Use `accessibilityLayer`.

**Key files:** `frontend/components/ui/chart.tsx`, `frontend/components/audit/`

## Redirect Pages Pattern

Use redirect pages for backward compatibility when restructuring routes:

```tsx
import { redirect } from "next/navigation";
export default function BackupRedirect() { redirect("/configuration/backup"); }
```

Used in 14+ pages to preserve bookmarks when routes are reorganized.

**Key files:** `frontend/components/`, `frontend/lib/`, `frontend/components/ui/`

**Related:** [Anti-patterns: Frontend](../anti-patterns/frontend.md)
