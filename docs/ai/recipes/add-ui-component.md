# Recipe: Add UI Component

Create a reusable UI component with variants, sizes, and proper TypeScript types.

## When to Use

- Creating shared components used in multiple places
- Building components with multiple display modes (variants)
- Components that need size variations
- Components with image/icon support and fallbacks

## Prerequisites

Read these files first:
- `frontend/components/logo.tsx` - Reference implementation
- `frontend/lib/utils.ts` - The `cn()` utility
- `frontend/components/ui/` - shadcn components for composition

## Steps

### 1. Create the Component File

Create `frontend/components/{component-name}.tsx`:

```tsx
"use client";

import { useState } from "react";
import { cn } from "@/lib/utils";

// Step 1: Define the props interface with JSDoc comments
export interface ComponentNameProps {
  /** Display variant */
  variant?: "full" | "compact" | "icon";
  /** Size preset */
  size?: "sm" | "md" | "lg";
  /** Additional CSS classes */
  className?: string;
}
```

### 2. Define Size Configuration

Create a size config object for consistent sizing across variants:

```tsx
const sizeConfig = {
  sm: {
    container: "h-6 w-6",
    text: "text-sm",
    gap: "gap-1.5",
  },
  md: {
    container: "h-8 w-8",
    text: "text-base",
    gap: "gap-2",
  },
  lg: {
    container: "h-10 w-10",
    text: "text-lg",
    gap: "gap-2.5",
  },
};
```

### 3. Implement the Component

```tsx
export function ComponentName({
  variant = "full",
  size = "md",
  className,
}: ComponentNameProps) {
  const sizes = sizeConfig[size];

  // Handle different variants
  if (variant === "icon") {
    return (
      <div className={cn(sizes.container, "...", className)}>
        {/* Icon-only content */}
      </div>
    );
  }

  // Default/full variant
  return (
    <div className={cn("flex items-center", sizes.gap, className)}>
      {/* Full content */}
    </div>
  );
}
```

### 4. Add Image Support with Fallback (if needed)

```tsx
import Image from "next/image";

export function ComponentName({ src, label, ...props }: ComponentNameProps) {
  const [imageError, setImageError] = useState(false);
  const hasImage = src && !imageError;

  return (
    <div>
      {hasImage ? (
        <Image
          src={src}
          alt={label}
          fill
          className="object-contain"
          onError={() => setImageError(true)}
        />
      ) : (
        // Fallback content (e.g., initials, icon, placeholder)
        <div className="...">{label.slice(0, 2)}</div>
      )}
    </div>
  );
}
```

### 5. Use the Component

```tsx
import { ComponentName } from "@/components/component-name";

// With defaults
<ComponentName />

// With props
<ComponentName variant="compact" size="lg" className="my-4" />
```

## Checklist

- [ ] TypeScript interface with JSDoc comments for all props
- [ ] Size configuration object (if multiple sizes)
- [ ] Default values for optional props
- [ ] Uses `cn()` for class merging
- [ ] Handles image errors gracefully (if applicable)
- [ ] `"use client"` directive (if using hooks/state)
- [ ] Exported from component file

## File Naming

| Type | Pattern | Example |
|------|---------|---------|
| Component file | `kebab-case.tsx` | `user-avatar.tsx` |
| Props interface | `PascalCaseProps` | `UserAvatarProps` |
| Component function | `PascalCase` | `UserAvatar` |

## Common Patterns

### Composition with shadcn

```tsx
import { Button } from "@/components/ui/button";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";

export function UserButton({ user }: { user: User }) {
  return (
    <Button variant="ghost" className="gap-2">
      <Avatar className="h-6 w-6">
        <AvatarImage src={user.avatar} />
        <AvatarFallback>{user.initials}</AvatarFallback>
      </Avatar>
      {user.name}
    </Button>
  );
}
```

### Using App Config

```tsx
import { APP_CONFIG } from "@/config/app";

export function AppLogo() {
  return <span>{APP_CONFIG.name}</span>;
}
```

### Forwarding Refs (for composability)

```tsx
import { forwardRef } from "react";

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "default" | "outline";
}

export const CustomButton = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant = "default", className, ...props }, ref) => {
    return (
      <button
        ref={ref}
        className={cn(variants[variant], className)}
        {...props}
      />
    );
  }
);
CustomButton.displayName = "CustomButton";
```

## Reference Implementation

See `frontend/components/logo.tsx` for a complete example with:
- Three variants (full, icon, text)
- Three sizes (sm, md, lg)
- Image support with text fallback
- App config integration
