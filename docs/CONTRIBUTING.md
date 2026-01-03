# DanaVision Development Guidelines

This document outlines critical architectural patterns and rules that **MUST** be followed when making changes to DanaVision. Breaking these patterns can cause data leaks, security vulnerabilities, or application failures.

---

## 🚨 STOP: Read This First!

> **⚠️ CRITICAL: Before writing ANY code, you MUST understand these requirements. Your PR will be rejected if these are not met.**

### Quick Checklist

- [ ] **Tests are written** - Every feature/bugfix requires tests
- [ ] **Tests pass** - Run full test suite before submitting
- [ ] **E2E tests pass** - Frontend features require Playwright tests
- [ ] **ADR created** - If this is a significant change
- [ ] **Documentation updated** - TypeScript types, API docs if needed

### Quick Reference: Requirements by Task Type

| Task Type | Backend Test | E2E Test | ADR Required? | Documentation Required? |
|-----------|-------------|----------|---------------|------------------------|
| New page | ✅ Pest feature test | ✅ Playwright spec | If new pattern | Update TypeScript types |
| New API endpoint | ✅ Pest feature test | If UI uses it | If significant | Update TypeScript types |
| Bug fix | ✅ Regression test | If UI affected | ❌ No | ❌ No |
| New component | ❌ No | ✅ If interactive | ❌ No | ❌ No |
| Database schema change | ✅ Migration test | ✅ If UI affected | ✅ Always | Update model docs |
| AI system changes | ✅ Service tests | ✅ If UI affected | ✅ Always | Update AI docs |

---

## ⚠️ Mandatory Requirements

### 1. Tests Are Required

> **🚨 EVERY new feature, bug fix, or change MUST include tests. No exceptions.**

| Change Type | Required Tests | Test Location |
|-------------|----------------|---------------|
| New API endpoint/page | Pest PHP feature test | `backend/tests/Feature/` |
| New frontend page | Playwright E2E test | `backend/e2e/` |
| Bug fix | Regression test | Same location as feature |
| New service/utility | Pest PHP unit test | `backend/tests/Unit/` |

```bash
# Run all tests before submitting changes

# Backend (Pest PHP)
cd backend && ./vendor/bin/pest

# E2E (Playwright) - requires running app
cd backend && npm run test:e2e

# E2E with UI mode for debugging
cd backend && npm run test:e2e:ui
```

### 2. E2E Tests for Frontend Features

> **🚨 ALL new frontend features MUST have Playwright E2E tests before being marked complete.**

**Why E2E tests matter:**
- Verifies the entire user flow works
- Catches integration issues between frontend and backend
- Ensures UI renders correctly
- Tests both desktop and mobile viewports

**E2E test requirements:**
- Test files in `backend/e2e/`
- Use `auth.setup.ts` for authenticated tests
- Cover happy path and common error states
- Test mobile viewport if responsive

### 3. ADRs for Significant Changes

ADRs MUST be written for:
- ✅ New features that introduce new patterns
- ✅ Database schema changes
- ✅ Authentication/authorization changes
- ✅ New external integrations
- ✅ AI system changes
- ✅ Infrastructure changes

**ADR Location**: `docs/adr/`
**ADR Template**: See [docs/adr/README.md](adr/README.md)

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              FRONTEND (Inertia + React)                      │
├─────────────────────────────────────────────────────────────────────────────┤
│  App.tsx ──► Pages (resources/js/Pages/) ──► Components                      │
│      │                                            │                          │
│      ▼                                            ▼                          │
│  AppLayout.tsx                              UI Components                    │
│  (Navigation, Theme)                        (resources/js/Components/)       │
│                                                   │                          │
│                                                   ▼                          │
│                                    Types (resources/js/types/index.ts)       │
└─────────────────────────────────────────────────────────────────────────────┘
                                           │
                                    Inertia Requests
                                           │
┌─────────────────────────────────────────────────────────────────────────────┐
│                            BACKEND (Laravel 11)                              │
├─────────────────────────────────────────────────────────────────────────────┤
│  routes/web.php ──► Controllers ──► Policies ──► Models ──► Database         │
│                          │              │                                    │
│                          ▼              ▼                                    │
│                      Services      $this->authorize()                        │
│                   (AI, PriceApi,        │                                    │
│                     Mail)               ▼                                    │
│                                   user_id CHECK                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Critical Rule #1: User-Based Data Isolation

**THIS IS THE MOST IMPORTANT SECURITY PATTERN IN THE APPLICATION.**

All user data is isolated by `user_id`. Users can ONLY access data belonging to them. Breaking this pattern creates **security vulnerabilities**.

### Required Patterns

#### Listing Resources (Index Actions)

```php
// ✅ CORRECT - Always filter by authenticated user's id
public function index(Request $request)
{
    $lists = ShoppingList::where('user_id', $request->user()->id)
        ->withCount('items')
        ->get();
    
    return Inertia::render('Lists/Index', ['lists' => $lists]);
}

// ❌ WRONG - Never return all records without user filter
public function index()
{
    $lists = ShoppingList::all(); // SECURITY VULNERABILITY!
    return Inertia::render('Lists/Index', ['lists' => $lists]);
}
```

#### Creating Resources (Store Actions)

```php
// ✅ CORRECT - Always set user_id from authenticated user
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
    ]);

    $list = ShoppingList::create([
        'user_id' => $request->user()->id,
        ...$validated,
    ]);
    
    return redirect()->route('lists.show', $list);
}

// ❌ WRONG - Never trust client-provided user_id
public function store(Request $request)
{
    $list = ShoppingList::create($request->all()); // SECURITY VULNERABILITY!
    return redirect()->route('lists.show', $list);
}
```

#### Viewing/Updating/Deleting Resources

```php
// ✅ CORRECT - Always authorize with policy before action
public function show(ShoppingList $list)
{
    $this->authorize('view', $list);  // Policy checks user_id match
    
    return Inertia::render('Lists/Show', ['list' => $list]);
}

public function destroy(ShoppingList $list)
{
    $this->authorize('delete', $list);  // Policy checks user_id match
    
    $list->delete();
    return redirect()->route('lists.index');
}

// ❌ WRONG - Never skip authorization
public function destroy(ShoppingList $list)
{
    $list->delete();  // Any user can delete any list! VULNERABILITY!
    return redirect()->route('lists.index');
}
```

### Policy Pattern

All policies MUST check user_id:

```php
class ShoppingListPolicy
{
    public function view(User $user, ShoppingList $list): bool
    {
        return $user->id === $list->user_id;
    }

    public function update(User $user, ShoppingList $list): bool
    {
        return $user->id === $list->user_id;
    }

    public function delete(User $user, ShoppingList $list): bool
    {
        return $user->id === $list->user_id;
    }
}
```

### Models That Require user_id

| Model | Has user_id | Notes |
|-------|------------|-------|
| ShoppingList | ✅ | Core entity |
| ListItem | ❌ | Accessed via ShoppingList (list.user_id) |
| AIProvider | ✅ | User's AI configurations |
| Setting | ✅ | User's settings |
| SearchHistory | ✅ | User's search history |

---

## Critical Rule #2: Frontend-Backend API Contract

These files define the API contract and **MUST stay synchronized**:

1. **`backend/resources/js/types/index.ts`** - TypeScript interfaces
2. **`backend/app/Http/Controllers/*Controller.php`** - Response data

### When Adding a New Field

1. Add column to migration
2. Add to model's `$fillable` array
3. Add to controller response (Inertia::render)
4. Add to TypeScript interface

### Example: Adding a field to ListItem

```php
// 1. Migration
Schema::table('list_items', function (Blueprint $table) {
    $table->string('sku')->nullable();
});

// 2. Model fillable (app/Models/ListItem.php)
protected $fillable = [
    // ... existing fields
    'sku',
];

// 3. Controller response includes the field automatically via model
```

```typescript
// 4. TypeScript type (resources/js/types/index.ts)
export interface ListItem {
  // ... existing fields
  sku?: string;
}
```

---

## Critical Rule #3: Inertia.js Patterns

### Page Components

```tsx
import { Head, useForm } from '@inertiajs/react';
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

### Form Handling

```tsx
const { data, setData, post, processing, errors } = useForm({
  product_name: '',
  target_price: '',
});

const submit = (e: FormEvent) => {
  e.preventDefault();
  post(`/lists/${list.id}/items`, {
    onSuccess: () => {
      reset();
    },
  });
};
```

### Navigation

```tsx
import { router } from '@inertiajs/react';

// Programmatic navigation
router.get('/lists');
router.post('/items/1/refresh');

// Link navigation
<Link href="/lists">Back to Lists</Link>
```

---

## Testing Patterns

### Backend Feature Test Example

```php
// tests/Feature/ShoppingListTest.php
use App\Models\ShoppingList;
use App\Models\User;

test('user can create shopping list', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->post('/lists', [
            'name' => 'Test List',
        ]);
    
    $response->assertRedirect();
    
    $this->assertDatabaseHas('shopping_lists', [
        'name' => 'Test List',
        'user_id' => $user->id,
    ]);
});

test('user cannot access other user lists', function () {
    $user = User::factory()->create();
    $otherList = ShoppingList::factory()->create(); // Different user
    
    $response = $this->actingAs($user)
        ->get("/lists/{$otherList->id}");
    
    $response->assertStatus(403);
});
```

### E2E Test Example

```typescript
// e2e/lists.spec.ts
import { test, expect } from '@playwright/test';

test('user can create a new shopping list', async ({ page }) => {
  await page.goto('/lists/create');
  
  await page.fill('input[name="name"]', 'My New List');
  await page.click('button[type="submit"]');
  
  await expect(page).toHaveURL(/\/lists\/\d+/);
  await expect(page.locator('h1')).toContainText('My New List');
});
```

---

## Pre-Submission Checklist

### 🔒 Security & Architecture (MANDATORY)
- [ ] User isolation is maintained (no cross-user data access)
- [ ] New resources have appropriate Policy with user_id checks
- [ ] Controllers call `$this->authorize()` for protected actions
- [ ] New fields added to Model AND TypeScript type

### 🧪 Testing (MANDATORY)
- [ ] **Backend tests pass**: `cd backend && ./vendor/bin/pest`
- [ ] **E2E tests pass**: `cd backend && npm run test:e2e`
- [ ] **New features have tests**: Backend + E2E
- [ ] **Bug fixes include regression test**

### 📝 Documentation (MANDATORY for significant changes)
- [ ] **ADR created** for architectural decisions
- [ ] **TypeScript types updated** if response changed
- [ ] **README updated** if setup process changed

### ✨ Code Quality
- [ ] Code follows existing patterns
- [ ] No debug/console.log statements
- [ ] Loading/error states handled in UI

---

## Common Patterns Reference

### Adding a New Page

1. Create controller method returning `Inertia::render()`
2. Add route in `routes/web.php`
3. Create page component in `resources/js/Pages/`
4. Add navigation link if needed
5. Update TypeScript types if new data structures
6. Write Pest feature test
7. Write Playwright E2E test

### Adding a Field to Existing Resource

1. Migration: Add column
2. Model: Add to `$fillable`, add cast if needed
3. Controller: Include in response (usually automatic)
4. TypeScript: Update interface
5. Frontend: Update forms/displays
6. Tests: Update existing tests

---

## Questions?

If unsure whether a change is safe:

1. Check if the pattern exists elsewhere in the codebase
2. Look for the `user_id` filtering pattern
3. Verify Policy authorization is in place
4. Ensure TypeScript types match responses
5. **Run tests** - they catch many issues
