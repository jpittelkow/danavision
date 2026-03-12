# Recipe: Extend the Onboarding Wizard

Add a new step to the user onboarding wizard.

## Key Files

| File | Purpose |
|------|---------|
| `backend/app/Http/Controllers/Api/OnboardingController.php` | API for wizard status and step completion |
| `backend/app/Models/UserOnboarding.php` | Per-user onboarding state model |

## API Reference

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/onboarding/status` | Get wizard status + completed steps |
| `POST` | `/api/onboarding/complete-wizard` | Mark wizard as fully completed |
| `POST` | `/api/onboarding/dismiss-wizard` | Dismiss without completing |
| `POST` | `/api/onboarding/complete-step` | Mark a single step complete (`{ step: "step_name" }`) |
| `POST` | `/api/onboarding/reset-wizard` | Reset to show wizard again |

## Adding a New Step

### 1. Define the Step Name

Use descriptive kebab-case names: `setup-profile`, `configure-notifications`, `connect-storage`.

### 2. Frontend: Add Step to Wizard UI

```tsx
const ONBOARDING_STEPS = [
  { key: "setup-profile", title: "Set Up Profile", component: <SetupProfile /> },
  { key: "my-new-step", title: "My New Step", component: <MyNewStep /> },
  // ...
];
```

### 3. Mark Step Complete

```typescript
await apiFetch("/api/onboarding/complete-step", {
  method: "POST",
  body: JSON.stringify({ step: "my-new-step" }),
});
```

### 4. Check Completion

```typescript
const { data } = await apiFetch("/api/onboarding/status");
// data.steps_completed: string[] — list of completed step keys
// data.show_wizard: boolean — true if wizard should be shown
```

## How It Works

- `UserOnboarding::forUser($user)` — finds or creates the onboarding record
- `steps_completed` is a JSON array of step keys
- `wizard_completed_at` / `wizard_dismissed_at` — timestamps for full completion/dismissal
- `show_wizard` is `true` when neither completed nor dismissed

**Related:** [Pattern: Controller](../patterns/controller.md)
