# Recipe: Make Component Responsive

Step-by-step guide to make any component responsive and mobile-friendly.

## When to Use This Recipe

- Creating new UI components
- Updating existing components to support mobile
- Fixing mobile layout issues
- Adding mobile-specific features (drawer nav, etc.)

## Quick Reference

| Concept | Mobile | Desktop |
|---------|--------|---------|
| Breakpoint prefix | (none) | `md:`, `lg:`, `xl:` |
| Layout | `flex-col`, `grid-cols-1` | `flex-row`, `grid-cols-2+` |
| Sidebar | Sheet (drawer) | Fixed sidebar |
| Touch target | Min 44px | Can be smaller |
| Typography | `text-sm`, `text-base` | `text-lg`, `text-xl` |

## Step 1: Identify the Component Type

Different components need different responsive strategies:

| Component Type | Strategy | Example |
|----------------|----------|---------|
| **Layout containers** | CSS Grid/Flexbox with breakpoints | Page layouts, card grids |
| **Navigation** | Conditional render (desktop/mobile) | Sidebar, tabs |
| **Data tables** | Horizontal scroll or card view | User table, logs |
| **Forms** | Stack fields on mobile | Settings forms |
| **Modals/Dialogs** | Full-screen on mobile (Sheet) | Edit dialogs |

## Step 2: Mobile-First Base Styles

Start with mobile styles (no prefix), then add larger screen styles:

```tsx
// ✅ CORRECT: Mobile-first
<div className="
  flex flex-col      // Mobile: stack vertically
  gap-4              // Mobile: base spacing
  p-4                // Mobile: base padding
  md:flex-row        // Tablet+: side by side
  md:gap-6           // Tablet+: more spacing
  lg:p-8             // Desktop: more padding
">
```

### Layout Container Example

```tsx
// Page with sidebar content pattern
<div className="flex flex-col md:flex-row min-h-screen">
  {/* Sidebar - hidden on mobile, shown on md+ */}
  <aside className="hidden md:flex md:w-64 md:flex-col border-r">
    <Navigation />
  </aside>
  
  {/* Main content - full width on mobile */}
  <main className="flex-1 p-4 md:p-6 lg:p-8">
    {children}
  </main>
</div>
```

### Card Grid Example

```tsx
// Cards: 1 column mobile → 2 tablet → 3 desktop
<div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
  {items.map(item => (
    <Card key={item.id}>
      <CardContent>{item.name}</CardContent>
    </Card>
  ))}
</div>
```

## Step 3: Handle Navigation Components

For sidebar/navigation that needs fundamentally different mobile UI:

```tsx
"use client";

import { useIsMobile } from "@/lib/use-mobile";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { Menu } from "lucide-react";

export function ResponsiveSidebar({ children }: { children: React.ReactNode }) {
  const isMobile = useIsMobile();
  const [open, setOpen] = useState(false);

  // Mobile: Use Sheet (drawer)
  if (isMobile) {
    return (
      <>
        <Button
          variant="ghost"
          size="icon"
          className="md:hidden"
          onClick={() => setOpen(true)}
        >
          <Menu className="h-5 w-5" />
        </Button>
        <Sheet open={open} onOpenChange={setOpen}>
          <SheetContent side="left" className="w-64 p-0">
            <nav onClick={() => setOpen(false)}>
              {children}
            </nav>
          </SheetContent>
        </Sheet>
      </>
    );
  }

  // Desktop: Fixed sidebar
  return (
    <aside className="hidden md:flex md:w-64 md:flex-col border-r">
      {children}
    </aside>
  );
}
```

## Step 4: Handle Data Tables

Tables often overflow on mobile. Two approaches:

### Approach A: Horizontal Scroll (Simpler)

```tsx
// Wrap table in scrollable container
<div className="overflow-x-auto rounded-md border">
  <Table className="min-w-[600px]">
    <TableHeader>
      <TableRow>
        <TableHead>Name</TableHead>
        <TableHead>Email</TableHead>
        <TableHead>Status</TableHead>
        <TableHead>Actions</TableHead>
      </TableRow>
    </TableHeader>
    <TableBody>
      {data.map(row => (
        <TableRow key={row.id}>
          {/* ... */}
        </TableRow>
      ))}
    </TableBody>
  </Table>
</div>
```

### Approach B: Card View on Mobile (Better UX)

```tsx
import { useIsMobile } from "@/lib/use-mobile";

export function ResponsiveDataView({ data }: { data: Item[] }) {
  const isMobile = useIsMobile();

  if (isMobile) {
    return (
      <div className="space-y-4">
        {data.map(item => (
          <Card key={item.id}>
            <CardHeader>
              <CardTitle className="text-base">{item.name}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm space-y-1">
              <p><span className="text-muted-foreground">Email:</span> {item.email}</p>
              <p><span className="text-muted-foreground">Status:</span> {item.status}</p>
            </CardContent>
            <CardFooter>
              <Button size="sm">Edit</Button>
            </CardFooter>
          </Card>
        ))}
      </div>
    );
  }

  return (
    <Table>
      {/* Desktop table view */}
    </Table>
  );
}
```

## Step 5: Handle Forms

Forms should stack on mobile with full-width inputs:

```tsx
<form className="space-y-4">
  {/* Fields stack on mobile, side-by-side on md+ */}
  <div className="grid gap-4 md:grid-cols-2">
    <div className="space-y-2">
      <Label htmlFor="firstName">First Name</Label>
      <Input id="firstName" className="w-full" />
    </div>
    <div className="space-y-2">
      <Label htmlFor="lastName">Last Name</Label>
      <Input id="lastName" className="w-full" />
    </div>
  </div>

  {/* Full-width field */}
  <div className="space-y-2">
    <Label htmlFor="email">Email</Label>
    <Input id="email" type="email" className="w-full" />
  </div>

  {/* Button with adequate touch target */}
  <Button type="submit" className="w-full md:w-auto min-h-[44px]">
    Save Changes
  </Button>
</form>
```

## Step 6: Ensure Touch Targets

All interactive elements need minimum 44x44px touch targets:

```tsx
// ✅ CORRECT: Adequate touch targets
<Button className="min-h-[44px]">Submit</Button>

<button className="p-3 hover:bg-accent rounded-md">
  <Icon className="h-5 w-5" /> {/* 20px icon + 24px padding = 44px */}
</button>

// Icon button with explicit size
<Button variant="ghost" size="icon" className="h-11 w-11">
  <Settings className="h-5 w-5" />
</Button>

// ❌ WRONG: Too small for touch
<button className="p-1"><Icon className="h-4 w-4" /></button>
```

## Step 7: Responsive Typography

Use responsive text sizes:

```tsx
// Page titles
<h1 className="text-2xl font-bold md:text-3xl lg:text-4xl">
  Dashboard
</h1>

// Section headers
<h2 className="text-xl font-semibold md:text-2xl">
  Recent Activity
</h2>

// Body text
<p className="text-sm md:text-base text-muted-foreground">
  Description text that should be readable on all devices.
</p>
```

## Step 8: Test Your Changes

Run through this checklist:

```markdown
- [ ] **320px** (small mobile) - No overflow, content readable
- [ ] **375px** (iPhone) - Layout works, touch targets adequate
- [ ] **414px** (large phone) - Smooth transition
- [ ] **768px** (tablet) - md: styles applied correctly
- [ ] **1024px** (laptop) - lg: styles applied correctly
- [ ] **1280px+** (desktop) - Full desktop experience

Interaction tests:
- [ ] All buttons tappable (44px minimum)
- [ ] Forms usable with mobile keyboard
- [ ] Navigation works (sidebar/drawer)
- [ ] Scrolling is smooth
- [ ] No accidental touches due to tight spacing
```

## Complete Example: Responsive Settings Page

```tsx
"use client";

import { useState } from "react";
import { useIsMobile } from "@/lib/use-mobile";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";

export default function SettingsPage() {
  const isMobile = useIsMobile();

  return (
    <div className="space-y-6">
      {/* Responsive page header */}
      <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 className="text-2xl font-bold md:text-3xl">Settings</h1>
          <p className="text-sm text-muted-foreground md:text-base">
            Manage your account settings.
          </p>
        </div>
        <Button className="w-full md:w-auto min-h-[44px]">
          Save All Changes
        </Button>
      </div>

      {/* Tabs: scrollable on mobile */}
      <Tabs defaultValue="profile" className="space-y-4">
        <div className="overflow-x-auto">
          <TabsList className="inline-flex min-w-full md:min-w-0">
            <TabsTrigger value="profile" className="min-h-[44px]">Profile</TabsTrigger>
            <TabsTrigger value="security" className="min-h-[44px]">Security</TabsTrigger>
            <TabsTrigger value="notifications" className="min-h-[44px]">Notifications</TabsTrigger>
          </TabsList>
        </div>

        <TabsContent value="profile">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg md:text-xl">Profile Information</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="space-y-4">
                {/* Stack on mobile, 2 columns on md+ */}
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="name">Name</Label>
                    <Input id="name" className="w-full" />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" type="email" className="w-full" />
                  </div>
                </div>
                <Button type="submit" className="w-full md:w-auto min-h-[44px]">
                  Update Profile
                </Button>
              </form>
            </CardContent>
          </Card>
        </TabsContent>

        {/* Other tab contents... */}
      </Tabs>
    </div>
  );
}
```

## Common Patterns Quick Reference

### Hide/Show Based on Screen Size

```tsx
<div className="block md:hidden">Mobile only</div>
<div className="hidden md:block">Desktop only</div>
```

### Responsive Flex Direction

```tsx
<div className="flex flex-col md:flex-row">
```

### Responsive Grid Columns

```tsx
<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
```

### Responsive Spacing

```tsx
<div className="p-4 md:p-6 lg:p-8">
<div className="gap-4 md:gap-6">
<div className="space-y-4 md:space-y-6">
```

### Responsive Text

```tsx
<h1 className="text-xl md:text-2xl lg:text-3xl">
<p className="text-sm md:text-base">
```

## Checklist

Before completing responsive work:

- [ ] Base styles target mobile (no prefix)
- [ ] Breakpoint modifiers add larger screen styles
- [ ] Touch targets are 44px minimum
- [ ] Navigation works on mobile (drawer/hamburger)
- [ ] Tables scroll or convert to cards
- [ ] Forms stack vertically on mobile
- [ ] Typography scales appropriately
- [ ] Tested across viewport sizes

## Related Documentation

- [ADR-013: Responsive Mobile-First Design](../../adr/013-responsive-mobile-first-design.md)
- [Responsive Design Pattern](../patterns/responsive.md)
- [Mobile Responsiveness Roadmap](../../plans/mobile-responsive-roadmap.md)
- `frontend/lib/use-mobile.ts` - Mobile detection hook

### Implementation Journal

- [Mobile Responsiveness Implementation (2026-01-27)](../../journal/2026-01-27-mobile-responsiveness-implementation.md)
