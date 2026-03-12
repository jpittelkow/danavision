# ADR-008: Testing Strategy

## Status

Accepted

## Date

2026-01-24

## Context

Sourdough needs comprehensive testing to ensure reliability:
- Backend API must be thoroughly tested
- Frontend components need unit and integration tests
- Critical user flows require end-to-end testing
- CI/CD pipeline must run tests automatically

We need a testing strategy that balances coverage with development velocity.

## Decision

We will implement a **multi-layer testing strategy** with Pest PHP (backend), Vitest (frontend), and Playwright (E2E).

### Testing Pyramid

```
                    ┌───────────┐
                   /    E2E     \        ~10%
                  /   Playwright  \       Slow, high confidence
                 /─────────────────\
                /   Integration     \    ~30%
               /    API + Component  \   Medium speed
              /───────────────────────\
             /         Unit            \  ~60%
            /   Services + Functions    \ Fast, isolated
           /─────────────────────────────\
```

### Backend Testing (Pest PHP)

#### Unit Tests
Test individual services and classes in isolation.

```php
// tests/Unit/Services/TwoFactorServiceTest.php
it('generates valid TOTP secret', function () {
    $service = new TwoFactorService();
    $secret = $service->generateSecret();
    
    expect($secret)->toHaveLength(32);
    expect($secret)->toMatch('/^[A-Z2-7]+$/');
});

it('verifies correct TOTP code', function () {
    $service = new TwoFactorService();
    $secret = 'JBSWY3DPEHPK3PXP';
    
    $code = $service->getCurrentCode($secret);
    
    expect($service->verify($secret, $code))->toBeTrue();
});
```

#### Feature Tests
Test API endpoints with database interactions.

```php
// tests/Feature/AuthTest.php
it('registers a new user', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure(['user' => ['id', 'name', 'email']]);
    
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});

it('prevents login with invalid credentials', function () {
    User::factory()->create(['email' => 'test@example.com']);
    
    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);
    
    $response->assertStatus(401);
});
```

### Frontend Testing (Vitest + React Testing Library)

#### Component Tests

```typescript
// components/ui/__tests__/Button.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { Button } from '../button';

describe('Button', () => {
  it('renders with children', () => {
    render(<Button>Click me</Button>);
    expect(screen.getByRole('button')).toHaveTextContent('Click me');
  });

  it('calls onClick when clicked', () => {
    const handleClick = vi.fn();
    render(<Button onClick={handleClick}>Click</Button>);
    
    fireEvent.click(screen.getByRole('button'));
    
    expect(handleClick).toHaveBeenCalledOnce();
  });

  it('is disabled when disabled prop is true', () => {
    render(<Button disabled>Click</Button>);
    expect(screen.getByRole('button')).toBeDisabled();
  });
});
```

#### Hook Tests

```typescript
// lib/__tests__/useAuth.test.ts
import { renderHook, act } from '@testing-library/react';
import { useAuth } from '../auth';

describe('useAuth', () => {
  it('starts with no user', () => {
    const { result } = renderHook(() => useAuth());
    expect(result.current.user).toBeNull();
  });

  it('sets user after login', async () => {
    const { result } = renderHook(() => useAuth());
    
    await act(async () => {
      await result.current.login('test@example.com', 'password');
    });
    
    expect(result.current.user).not.toBeNull();
  });
});
```

### End-to-End Testing (Playwright)

#### Critical Path Tests

```typescript
// e2e/auth.spec.ts
import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
  test('user can register and login', async ({ page }) => {
    // Register
    await page.goto('/register');
    await page.fill('[name="name"]', 'Test User');
    await page.fill('[name="email"]', `test-${Date.now()}@example.com`);
    await page.fill('[name="password"]', 'password123');
    await page.fill('[name="password_confirmation"]', 'password123');
    await page.click('button[type="submit"]');
    
    // Should redirect to dashboard
    await expect(page).toHaveURL('/dashboard');
    await expect(page.locator('h1')).toContainText('Dashboard');
  });

  test('user can enable 2FA', async ({ page }) => {
    await login(page);
    
    await page.goto('/settings/security');
    await page.click('text=Enable Two-Factor');
    
    // Should show QR code
    await expect(page.locator('[data-testid="2fa-qr"]')).toBeVisible();
  });
});
```

### Test Database Strategy

```php
// Backend: Use SQLite in-memory for speed
// phpunit.xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>

// tests/TestCase.php
use RefreshDatabase;
```

### CI/CD Pipeline

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  backend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install
        working-directory: backend
      - run: php artisan test
        working-directory: backend

  frontend-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - run: npm ci
        working-directory: frontend
      - run: npm test
        working-directory: frontend

  e2e-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - run: docker-compose up -d
      - uses: actions/setup-node@v4
      - run: npx playwright install
      - run: npx playwright test
```

### Coverage Requirements

| Layer | Target Coverage | Enforcement |
|-------|-----------------|-------------|
| Backend Unit | 80% | CI warning |
| Backend Feature | 90% of endpoints | Required |
| Frontend Unit | 70% | CI warning |
| E2E | Critical paths | Required |

## Consequences

### Positive

- Pest PHP provides elegant, readable tests
- Vitest is fast with native ESM support
- Playwright handles all browsers
- In-memory SQLite speeds up backend tests
- CI catches issues before merge

### Negative

- E2E tests are slow and flaky-prone
- Multiple test frameworks to maintain
- Initial setup time significant
- Mock management can be complex

### Neutral

- Tests run in parallel where possible
- Coverage reports generated for PR review
- Flaky tests should be fixed or removed

## Related Decisions

- [ADR-001: Technology Stack](./001-technology-stack.md)

## Notes

### Test Naming Convention

- Backend: `it('does something')` using Pest
- Frontend: `it('should do something')` using Vitest
- E2E: `test('user can do something')` using Playwright

### When to Write Tests

1. **Always**: API endpoints, auth flows, business logic
2. **Usually**: UI components with logic, utilities
3. **Sometimes**: Simple presentational components
4. **Never**: External library wrappers (test behavior, not implementation)

### Mocking Strategy

- Backend: Mock external services (API calls, email)
- Frontend: Mock API responses with Vitest `vi.mock()` (direct module mocking)
- E2E: Use test database, real API

## Implementation Journal

- [Login Testing Review (2026-02-05)](../journal/2026-02-05-login-testing-review.md)
- [Login Testing Review Checklist](../journal/login-testing-review-checklist.md)
