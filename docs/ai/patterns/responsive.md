# Responsive & Mobile-First Patterns

Always write base styles for mobile, then add breakpoint modifiers for larger screens.

## Mobile-First Class Order

```tsx
// CORRECT: Mobile-first (base -> larger screens)
<div className="flex flex-col md:flex-row lg:gap-6">
<div className="w-full md:w-1/2 lg:w-1/3">
<div className="p-4 md:p-6 lg:p-8">
<h1 className="text-2xl md:text-3xl lg:text-4xl">

// WRONG: Desktop-first (breaks mobile)
<div className="flex-row md:flex-col">
<div className="w-1/3 md:w-full">
```

## Responsive Grid Layout

```tsx
// Cards: 1 column on mobile -> multi-column on larger screens
<div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
  {items.map(item => <Card key={item.id}>...</Card>)}
</div>

// Form fields: stack on mobile, side-by-side on tablet+
<div className="grid gap-4 md:grid-cols-2">
  <FormField name="firstName" />
  <FormField name="lastName" />
</div>
```

## Mobile Detection Hook

```tsx
import { useIsMobile } from "@/lib/use-mobile";

export function ResponsiveComponent() {
  const isMobile = useIsMobile();
  if (isMobile) return <MobileVersion />;
  return <DesktopVersion />;
}
```

**Key file:** `frontend/lib/use-mobile.ts`

## Responsive Navigation

```tsx
import { Sheet, SheetContent } from "@/components/ui/sheet";
import { useIsMobile } from "@/lib/use-mobile";

// Mobile: Sheet (drawer), Desktop: Fixed sidebar
export function ResponsiveSidebar({ children }) {
  const isMobile = useIsMobile();
  if (isMobile) {
    return (
      <Sheet open={open} onOpenChange={setOpen}>
        <SheetContent side="left" className="w-64 p-0">
          <nav onClick={() => setOpen(false)}>{children}</nav>
        </SheetContent>
      </Sheet>
    );
  }
  return <aside className="hidden md:flex md:w-64 md:flex-col border-r">{children}</aside>;
}
```

## Responsive Table

```tsx
// Option 1: Horizontal scroll wrapper
<div className="overflow-x-auto rounded-md border">
  <Table className="min-w-[600px]">{/* table content */}</Table>
</div>

// Option 2: Card view on mobile (better UX)
const isMobile = useIsMobile();
if (isMobile) {
  return <div className="space-y-4">{data.map(item => <Card>...</Card>)}</div>;
}
return <Table>{/* Desktop table view */}</Table>;
```

## Touch Target Pattern

Ensure all interactive elements have minimum 44x44px touch targets:

```tsx
// CORRECT: Adequate touch targets
<Button className="min-h-[44px]">Submit</Button>
<button className="p-3 hover:bg-accent rounded-md">
  <Icon className="h-5 w-5" />
</button>
<Button variant="ghost" size="icon" className="h-11 w-11">
  <Settings className="h-5 w-5" />
</Button>

// WRONG: Too small for touch
<button className="p-1"><Icon className="h-4 w-4" /></button>
```

## Responsive Typography

```tsx
<h1 className="text-2xl font-bold md:text-3xl lg:text-4xl">Dashboard</h1>
<h2 className="text-xl font-semibold md:text-2xl">Recent Activity</h2>
<p className="text-sm md:text-base text-muted-foreground">Description text.</p>
```

## Hide/Show by Breakpoint

```tsx
<div className="block md:hidden">Mobile only</div>
<div className="hidden md:block">Desktop only</div>
<div className="hidden sm:block">Tablet and up</div>
```

**Key files:** `frontend/lib/use-mobile.ts`, `frontend/components/app-shell.tsx`

**Related:** [Anti-patterns: Responsive](../anti-patterns/responsive.md)

## Implementation Journal

- [Mobile Responsiveness Implementation (2026-01-27)](../../journal/2026-01-27-mobile-responsiveness-implementation.md)
