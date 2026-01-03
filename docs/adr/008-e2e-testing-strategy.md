# ADR 008: E2E Testing Strategy with Playwright

## Status

Accepted

## Date

2026-01-02

## Context

DanaVision needs a robust testing strategy that ensures:

1. Frontend features work correctly end-to-end
2. User flows function as expected
3. Regressions are caught before deployment
4. Both desktop and mobile experiences are tested

Backend tests (Pest PHP) cover API and business logic, but don't verify the frontend renders and behaves correctly.

## Decision

We will implement **E2E Testing with Playwright** as a mandatory part of the development workflow.

### Testing Requirements

1. **All new frontend features MUST have E2E tests** before being marked complete
2. E2E tests run alongside backend tests
3. Tests must pass before code is merged
4. Both desktop and mobile viewports are tested

### Playwright Configuration

```typescript
// playwright.config.ts
export default defineConfig({
  testDir: './e2e',
  use: {
    baseURL: 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'setup', testMatch: /.*\.setup\.ts/ },
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
    {
      name: 'mobile',
      use: { ...devices['iPhone 13'] },
      dependencies: ['setup'],
    },
  ],
});
```

### Test Structure

```
backend/e2e/
├── auth.setup.ts       # Authentication for all tests
├── smart-add.spec.ts   # Smart Add feature tests
├── lists.spec.ts       # Shopping lists tests
├── search.spec.ts      # Search feature tests
└── dashboard.spec.ts   # Dashboard tests
```

### Authentication Setup

Tests share authentication state to avoid logging in every test:

```typescript
// auth.setup.ts
setup('authenticate', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[type="email"]', 'test@example.com');
  await page.fill('input[type="password"]', 'password');
  await page.click('button[type="submit"]');
  await page.context().storageState({ path: 'e2e/.auth/user.json' });
});
```

### Test Commands

```bash
npm run test:e2e          # Run all E2E tests
npm run test:e2e:ui       # Interactive UI mode
npm run test:e2e:headed   # See browser during tests
npm run test:e2e:debug    # Debug mode
```

### What to Test

| Category | Examples |
|----------|----------|
| Navigation | Links work, pages load |
| Forms | Submission, validation errors |
| CRUD | Create, read, update, delete |
| Auth | Login, logout, protected routes |
| Mobile | Menu toggle, touch targets |

### CI/CD Integration

Tests run automatically on push/PR:

```yaml
- name: Run E2E tests
  run: cd backend && npm run test:e2e
```

## Consequences

### Positive

- Catches frontend bugs before deployment
- Tests real user flows
- Mobile viewport testing included
- Visual regression detection (screenshots)
- Fast feedback in CI/CD

### Negative

- Tests require running application
- Slower than unit tests
- May be flaky if not written carefully
- Additional test maintenance

### Mitigation

- Use `waitForLoadState` and explicit waits
- Don't rely on timing
- Use specific selectors
- Reuse auth state across tests

## Related Decisions

- [ADR 004: Mobile-First Architecture](004-mobile-first-architecture.md) - Mobile testing
- [ADR 007: Smart Add Feature](007-smart-add-feature.md) - Feature requiring E2E tests
