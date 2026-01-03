# DanaVision React Frontend Documentation

## Overview

The DanaVision frontend is built with React 18 and TypeScript, served via Laravel Inertia.js. This provides SPA-like experience while maintaining server-side routing.

## Directory Structure

```
backend/resources/js/
├── app.tsx                 # Application entry point
├── vite-env.d.ts          # Vite type definitions
├── Components/            # Reusable UI components
│   ├── ui/               # Base UI components
│   │   ├── badge.tsx
│   │   ├── button.tsx
│   │   ├── card.tsx
│   │   ├── dialog.tsx
│   │   ├── dropdown-menu.tsx
│   │   ├── input.tsx
│   │   ├── label.tsx
│   │   ├── select.tsx
│   │   ├── switch.tsx
│   │   └── tabs.tsx
│   ├── ImageUpload.tsx   # Image upload component
│   ├── PriceChart.tsx    # Price history chart
│   └── ThemeToggle.tsx   # Dark/light mode toggle
├── hooks/                # Custom React hooks
│   └── useTheme.ts
├── Layouts/              # Page layouts
│   └── AppLayout.tsx     # Main authenticated layout
├── lib/                  # Utilities
│   └── utils.ts          # Helper functions
├── Pages/                # Page components (routes)
│   ├── Auth/
│   │   ├── Login.tsx
│   │   └── Register.tsx
│   ├── Items/
│   │   └── Show.tsx
│   ├── Lists/
│   │   ├── Create.tsx
│   │   ├── Index.tsx
│   │   └── Show.tsx
│   ├── Settings/
│   │   └── AI.tsx
│   ├── Dashboard.tsx
│   ├── Search.tsx
│   ├── Settings.tsx
│   └── SmartAdd.tsx
└── types/                # TypeScript definitions
    └── index.ts
```

## Inertia.js Integration

### App Entry Point

```tsx
// app.tsx
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true });
    return pages[`./Pages/${name}.tsx`];
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
```

### Page Component Pattern

```tsx
// Pages/Lists/Show.tsx
import { Head, Link, useForm, router } from '@inertiajs/react';
import { PageProps, ShoppingList } from '@/types';
import AppLayout from '@/Layouts/AppLayout';

interface Props extends PageProps {
  list: ShoppingList;
  can_edit: boolean;
}

export default function ListShow({ auth, list, can_edit, flash }: Props) {
  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title={list.name} />
      {/* Page content */}
    </AppLayout>
  );
}
```

## TypeScript Types

### Core Types

```typescript
// types/index.ts
export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at?: string;
  created_at: string;
  updated_at: string;
}

export interface ShoppingList {
  id: number;
  user_id: number;
  name: string;
  description?: string;
  items_count?: number;
  items?: ListItem[];
  created_at: string;
  updated_at: string;
}

export interface ListItem {
  id: number;
  shopping_list_id: number;
  product_name: string;
  product_url?: string;
  product_image_url?: string;
  sku?: string;
  current_price?: number;
  previous_price?: number;
  lowest_price?: number;
  target_price?: number;
  current_retailer?: string;
  notes?: string;
  priority: 'low' | 'medium' | 'high';
  is_purchased: boolean;
  vendor_prices?: VendorPrice[];
  created_at: string;
  updated_at: string;
}

export interface VendorPrice {
  id: number;
  vendor: string;
  current_price: number | null;
  previous_price?: number | null;
  on_sale: boolean;
  in_stock: boolean;
}

export type PageProps<T = {}> = T & {
  auth: { user: User | null };
  flash: {
    success?: string;
    error?: string;
    message?: string;
  };
};
```

## Components

### AppLayout

The main layout component wrapping authenticated pages:

```tsx
// Layouts/AppLayout.tsx
interface LayoutProps extends PageProps {
  children: React.ReactNode;
}

const navigation = [
  { name: 'Smart Add', href: '/smart-add', icon: Sparkles, primary: true },
  { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  { name: 'Lists', href: '/lists', icon: ListTodo },
  { name: 'Search', href: '/search', icon: Search },
  { name: 'Settings', href: '/settings', icon: Settings },
];

export default function AppLayout({ children, auth, flash }: LayoutProps) {
  return (
    <div className="min-h-screen bg-background">
      {/* Sidebar */}
      <aside>
        <nav>{/* Navigation items */}</nav>
      </aside>
      
      {/* Main content */}
      <div className="lg:ml-64">
        <header>{/* Header with theme toggle */}</header>
        <main>{children}</main>
      </div>
    </div>
  );
}
```

### ImageUpload

Reusable image upload component with camera support:

```tsx
// Components/ImageUpload.tsx
interface ImageUploadProps {
  onImageSelect: (base64: string) => void;
  onClear?: () => void;
  value?: string | null;
  isLoading?: boolean;
  maxSizeMB?: number;
  showPreview?: boolean;
  label?: string;
  error?: string;
}

export function ImageUpload({
  onImageSelect,
  onClear,
  value,
  isLoading,
  ...props
}: ImageUploadProps) {
  // Handles file selection, drag-drop, and camera capture
}
```

### UI Components

Base UI components built on Radix UI:

| Component | Description |
|-----------|-------------|
| Button | Click action button with variants |
| Card | Content container |
| Input | Text input field |
| Select | Dropdown select |
| Dialog | Modal dialog |
| Badge | Status/label badge |
| Tabs | Tab navigation |
| Switch | Toggle switch |

## Form Handling

### useForm Hook

```tsx
import { useForm } from '@inertiajs/react';

const { data, setData, post, processing, errors, reset } = useForm({
  product_name: '',
  target_price: '',
  notes: '',
});

const submit = (e: FormEvent) => {
  e.preventDefault();
  post(`/lists/${listId}/items`, {
    onSuccess: () => reset(),
  });
};
```

### Form Component Example

```tsx
<form onSubmit={submit}>
  <div>
    <label>Product Name</label>
    <Input
      value={data.product_name}
      onChange={e => setData('product_name', e.target.value)}
    />
    {errors.product_name && <p className="text-destructive">{errors.product_name}</p>}
  </div>
  
  <Button type="submit" disabled={processing}>
    {processing ? 'Adding...' : 'Add Item'}
  </Button>
</form>
```

## Navigation

### Link Component

```tsx
import { Link } from '@inertiajs/react';

<Link href="/lists">Back to Lists</Link>
<Link href={`/items/${item.id}`}>View Item</Link>
```

### Programmatic Navigation

```tsx
import { router } from '@inertiajs/react';

// GET request
router.get('/lists');

// POST request
router.post(`/items/${id}/refresh`);

// With callbacks
router.post('/logout', {}, {
  onSuccess: () => window.location.href = '/login',
});
```

## Styling

### Tailwind CSS

The application uses Tailwind CSS with custom theme variables:

```css
/* resources/css/app.css */
@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
  :root {
    --background: 0 0% 100%;
    --foreground: 222.2 84% 4.9%;
    --primary: 262.1 83.3% 57.8%;
    --primary-foreground: 210 40% 98%;
    /* ... more variables */
  }

  .dark {
    --background: 222.2 84% 4.9%;
    --foreground: 210 40% 98%;
    /* ... dark mode variables */
  }
}
```

### CSS Classes

```tsx
// Using cn() utility for conditional classes
import { cn } from '@/lib/utils';

<button className={cn(
  'px-4 py-2 rounded-lg',
  isActive && 'bg-primary text-primary-foreground',
  disabled && 'opacity-50 cursor-not-allowed'
)}>
  Click me
</button>
```

## Theme System

### Theme Toggle

```tsx
// Components/ThemeToggle.tsx
export function ThemeToggle() {
  const { theme, toggleTheme } = useTheme();
  
  return (
    <button onClick={toggleTheme}>
      {theme === 'dark' ? <Sun /> : <Moon />}
    </button>
  );
}
```

### useTheme Hook

```typescript
// hooks/useTheme.ts
export function useTheme() {
  const [theme, setTheme] = useState<'light' | 'dark'>('light');
  
  const toggleTheme = () => {
    const newTheme = theme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', newTheme);
  };
  
  return { theme, toggleTheme };
}
```

## Pages

### Smart Add Page

The Smart Add page allows AI-powered product identification:

```tsx
// Pages/SmartAdd.tsx
export default function SmartAdd({ auth, lists, analysis, price_results, flash }: Props) {
  const [mode, setMode] = useState<'idle' | 'image' | 'text'>('idle');
  
  // Image upload handling
  // Text search handling
  // Add to list flow
}
```

### Dashboard

```tsx
// Pages/Dashboard.tsx
export default function Dashboard({ auth, stats, recent_drops, flash }: Props) {
  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title="Dashboard" />
      {/* Welcome message */}
      {/* Quick stats */}
      {/* Recent price drops */}
      {/* Quick actions */}
    </AppLayout>
  );
}
```

### List Detail

```tsx
// Pages/Lists/Show.tsx
export default function ListShow({ auth, list, can_edit, flash }: Props) {
  return (
    <AppLayout auth={auth} flash={flash}>
      <Head title={list.name} />
      {/* List header with actions */}
      {/* Add item form */}
      {/* Item cards with prices */}
    </AppLayout>
  );
}
```

## Utilities

### lib/utils.ts

```typescript
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function isMobileDevice(): boolean {
  return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(
    navigator.userAgent
  );
}
```

## Best Practices

### Component Structure

1. Import statements
2. Type definitions
3. Helper functions
4. Component definition
5. Export

### State Management

- Use `useForm` for form state
- Use `useState` for local component state
- Props flow down from Inertia page data

### Error Handling

```tsx
{flash?.success && (
  <div className="bg-green-100 text-green-700 px-4 py-3 rounded">
    {flash.success}
  </div>
)}

{flash?.error && (
  <div className="bg-red-100 text-red-700 px-4 py-3 rounded">
    {flash.error}
  </div>
)}

{errors.field && (
  <p className="text-destructive text-sm">{errors.field}</p>
)}
```

### Loading States

```tsx
<Button disabled={processing}>
  {processing ? (
    <>
      <Loader2 className="h-4 w-4 animate-spin mr-2" />
      Loading...
    </>
  ) : (
    'Submit'
  )}
</Button>
```

## Testing

E2E tests use Playwright:

```bash
# Run E2E tests
npm run test:e2e

# Run with UI mode
npm run test:e2e:ui
```

See [DOCUMENTATION_TESTING.md](DOCUMENTATION_TESTING.md) for complete testing guide.
