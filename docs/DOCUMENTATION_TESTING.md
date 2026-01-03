# DanaVision Testing Documentation

## Overview

DanaVision uses two testing frameworks:

1. **Pest PHP** - Backend feature and unit tests
2. **Playwright** - Frontend E2E (end-to-end) tests

All new features MUST have both backend and E2E tests before being considered complete.

## Quick Start

```bash
# Run backend tests
cd backend && ./vendor/bin/pest

# Run E2E tests (requires running app at localhost:8080)
cd backend && npm run test:e2e

# Run E2E with UI mode
cd backend && npm run test:e2e:ui

# Run E2E in headed browser
cd backend && npm run test:e2e:headed
```

---

## Backend Testing (Pest PHP)

### Directory Structure

```
backend/tests/
├── Feature/              # Feature/integration tests
│   ├── ShoppingListTest.php
│   ├── ListItemTest.php
│   └── SmartAddTest.php
├── Unit/                 # Unit tests
│   └── Services/
├── Pest.php             # Pest configuration
└── TestCase.php         # Base test class
```

### Writing Feature Tests

```php
// tests/Feature/ShoppingListTest.php
<?php

use App\Models\User;
use App\Models\ShoppingList;

test('user can view their shopping lists', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->get('/lists');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => 
        $page->component('Lists/Index')
            ->has('lists', 1)
    );
});

test('user cannot view other users lists', function () {
    $user = User::factory()->create();
    $otherList = ShoppingList::factory()->create(); // Different user

    $response = $this->actingAs($user)
        ->get("/lists/{$otherList->id}");

    $response->assertStatus(403);
});

test('user can create shopping list', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/lists', [
            'name' => 'My Test List',
            'description' => 'A test list',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('shopping_lists', [
        'name' => 'My Test List',
        'user_id' => $user->id,
    ]);
});

test('user can delete their shopping list', function () {
    $user = User::factory()->create();
    $list = ShoppingList::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->delete("/lists/{$list->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('shopping_lists', ['id' => $list->id]);
});
```

### Testing Authentication

```php
test('guest cannot access lists', function () {
    $response = $this->get('/lists');
    $response->assertRedirect('/login');
});

test('authenticated user can access lists', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->get('/lists');
    
    $response->assertStatus(200);
});
```

### Testing Validation

```php
test('shopping list requires name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post('/lists', [
            'name' => '', // Empty name
        ]);

    $response->assertSessionHasErrors('name');
});
```

### Running Backend Tests

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/ShoppingListTest.php

# Run specific test
./vendor/bin/pest --filter="user can create shopping list"

# Run with coverage
./vendor/bin/pest --coverage

# Run in parallel
./vendor/bin/pest --parallel
```

---

## Frontend E2E Testing (Playwright)

### Directory Structure

```
backend/e2e/
├── .auth/                    # Auth state storage (gitignored)
├── auth.setup.ts            # Authentication setup
├── smart-add.spec.ts        # Smart Add tests
├── lists.spec.ts            # Lists tests
├── search.spec.ts           # Search tests
└── dashboard.spec.ts        # Dashboard tests
```

### Configuration

```typescript
// playwright.config.ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  retries: process.env.CI ? 2 : 0,
  
  use: {
    baseURL: 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    { name: 'setup', testMatch: /.*\.setup\.ts/ },
    {
      name: 'chromium',
      use: { 
        ...devices['Desktop Chrome'],
        storageState: 'e2e/.auth/user.json',
      },
      dependencies: ['setup'],
    },
    {
      name: 'mobile',
      use: { 
        ...devices['iPhone 13'],
        storageState: 'e2e/.auth/user.json',
      },
      dependencies: ['setup'],
    },
  ],
});
```

### Authentication Setup

```typescript
// e2e/auth.setup.ts
import { test as setup, expect } from '@playwright/test';

const authFile = 'e2e/.auth/user.json';

setup('authenticate', async ({ page }) => {
  await page.goto('/login');
  
  await page.fill('input[type="email"]', 'test@example.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  
  await expect(page).toHaveURL(/\/(dashboard|smart-add)/);
  await page.context().storageState({ path: authFile });
});
```

### Writing E2E Tests

```typescript
// e2e/lists.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Shopping Lists', () => {
  test('should display lists page', async ({ page }) => {
    await page.goto('/lists');
    await expect(page).toHaveTitle(/Lists/);
    await expect(page.locator('text=Create List')).toBeVisible();
  });

  test('should create a new list', async ({ page }) => {
    await page.goto('/lists/create');
    
    const listName = `Test List ${Date.now()}`;
    await page.fill('input[name="name"]', listName);
    await page.click('button[type="submit"]');
    
    await expect(page).toHaveURL(/\/lists\/\d+/);
  });

  test('should add item to list', async ({ page }) => {
    await page.goto('/lists');
    await page.click('a[href*="/lists/"]').first();
    
    await page.click('text=Add Item');
    await page.fill('input[name="product_name"]', 'Test Product');
    await page.click('button[type="submit"]');
    
    await expect(page.locator('text=Test Product')).toBeVisible();
  });
});
```

### Testing Mobile Viewport

```typescript
test.describe('Mobile Navigation', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should show mobile menu', async ({ page }) => {
    await page.goto('/dashboard');
    
    const menuButton = page.locator('button:has(svg.lucide-menu)');
    await expect(menuButton).toBeVisible();
    
    await menuButton.click();
    await expect(page.locator('nav >> text=Smart Add')).toBeVisible();
  });
});
```

### Common Patterns

#### Waiting for Navigation

```typescript
await page.click('button[type="submit"]');
await page.waitForURL(/\/lists\/\d+/);
```

#### Handling Dialogs

```typescript
page.on('dialog', dialog => dialog.accept());
await page.click('button:has-text("Delete")');
```

#### Checking for Elements

```typescript
// Element exists
await expect(page.locator('text=Success')).toBeVisible();

// Element contains text
await expect(page.locator('h1')).toContainText('My List');

// Element count
await expect(page.locator('.item-card')).toHaveCount(3);
```

#### Network Idle

```typescript
await page.goto('/lists');
await page.waitForLoadState('networkidle');
```

### Running E2E Tests

```bash
# Run all tests
npm run test:e2e

# Run with UI mode (interactive)
npm run test:e2e:ui

# Run in headed mode (see browser)
npm run test:e2e:headed

# Run specific test file
npx playwright test e2e/lists.spec.ts

# Run specific test
npx playwright test -g "should create a new list"

# Debug mode
npx playwright test --debug
```

### Viewing Reports

After running tests, view the HTML report:

```bash
npx playwright show-report
```

---

## Test Requirements by Feature Type

| Feature Type | Backend Test | E2E Test |
|-------------|-------------|----------|
| New page | ✅ Pest feature test | ✅ Playwright spec |
| New API endpoint | ✅ Pest feature test | If UI uses it |
| Bug fix | ✅ Regression test | If UI affected |
| New component | - | ✅ If interactive |
| Database change | ✅ Migration test | If UI affected |
| AI feature | ✅ Service test | ✅ Playwright spec |

---

## Best Practices

### Backend Tests

1. **Test user isolation** - Always verify users can't access other users' data
2. **Test validation** - Verify required fields and constraints
3. **Test authorization** - Verify policies work correctly
4. **Use factories** - Create test data with model factories
5. **Clean up** - Use database transactions (automatic in Pest)

### E2E Tests

1. **Test critical paths** - Focus on main user flows
2. **Use auth setup** - Don't log in every test
3. **Wait properly** - Use `waitForURL`, `waitForLoadState`
4. **Test mobile** - Include mobile viewport tests
5. **Handle async** - Account for network requests

### General

1. **Run tests before committing** - Don't break the build
2. **Write tests alongside features** - Not after
3. **Test edge cases** - Empty states, errors, limits
4. **Keep tests focused** - One assertion per test when possible

---

## CI/CD Integration

Tests run automatically on push/PR via GitHub Actions:

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: cd backend && composer install
      - name: Run tests
        run: cd backend && ./vendor/bin/pest

  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'
      - name: Install dependencies
        run: cd backend && npm ci
      - name: Install Playwright
        run: cd backend && npx playwright install chromium
      - name: Start application
        run: docker compose up -d
      - name: Run E2E tests
        run: cd backend && npm run test:e2e
```

---

## Troubleshooting

### Backend Tests

**Tests fail with database errors:**
```bash
php artisan migrate:fresh --env=testing
```

**Tests are slow:**
```bash
./vendor/bin/pest --parallel
```

### E2E Tests

**Auth fails:**
- Ensure test user exists in database
- Check `.auth/user.json` is being created
- Verify login page selectors match

**Elements not found:**
- Wait for page load: `await page.waitForLoadState('networkidle')`
- Use more specific selectors
- Check if element is in iframe

**Tests timeout:**
- Increase timeout in config
- Check if app is running
- Verify baseURL is correct

**Flaky tests:**
- Add explicit waits
- Don't rely on timing
- Use `expect().toBeVisible()` before interactions
