# Recipe: Add PWA Install Prompt

Implement a custom install prompt for a Progressive Web App: capture `beforeinstallprompt`, show an AlertDialog after 2+ visits, and offer "Install App" in settings.

## When to Use

- Adding or customizing the PWA install experience
- Showing an install banner only after multiple visits
- Offering a manual "Install App" option in user settings or menu

## Prerequisites

Read these first:

- [PWA roadmap](../../plans/pwa-roadmap.md) Phase 4
- [Pattern: PWA Install Prompt](../patterns/ui-patterns.md)
- `frontend/lib/use-install-prompt.ts`
- `frontend/components/install-prompt.tsx`

## Steps

### 1. Use the hook where you need install state

```tsx
import { useInstallPrompt } from "@/lib/use-install-prompt";

const { canPrompt, isInstalled, promptInstall, shouldShowBanner, dismissBanner } = useInstallPrompt();
```

- **canPrompt**: `true` when `beforeinstallprompt` has fired and the app is not installed.
- **isInstalled**: `true` when running in standalone display mode (PWA installed).
- **shouldShowBanner**: `true` when the banner should be shown (visit count >= 2, not dismissed, canPrompt).
- **promptInstall()**: Call to show the native install dialog; returns `{ outcome: "accepted" | "dismissed" } | null`.
- **dismissBanner(dontShowAgain?)**: Call when the user dismisses the banner; pass `true` to suppress for 30 days.

### 2. Render the global install banner in the app shell

The shared `InstallPrompt` component is already integrated in `frontend/components/app-shell.tsx`. It uses a standard shadcn `AlertDialog` that opens when `shouldShowBanner` is true and shows Install / Not now with a "Don't show again" checkbox. The AlertDialog ensures proper text color in both light and dark modes.

Do not duplicate the dialog in multiple layouts; keep it in the shell so it appears once per app load.

### 3. Add a manual "Install App" control (e.g. in settings)

In User Preferences or a header menu:

```tsx
const { canPrompt, isInstalled, promptInstall } = useInstallPrompt();

if (isInstalled) {
  return <p>App is installed. Open from home screen.</p>;
}
if (canPrompt) {
  return (
    <Button onClick={async () => await promptInstall()}>
      Install App
    </Button>
  );
}
return <p>Install is available in supported browsers via the browser menu.</p>;
```

### 4. Visit count and dismissal (localStorage)

- **Visit count**: Incremented each time a component that calls `useInstallPrompt()` mounts. The banner shows only when `visitCount >= 2`, so it may appear after two page views in the same session.
- **Dismissal**: Stored in localStorage (`pwa-install-dismissed-at`). If the user checks "Don't show again", the banner is suppressed for 30 days.

### 5. Browser support

- **beforeinstallprompt**: Chrome, Edge; not Firefox; Safari uses manual "Add to Home Screen."
- **Display mode**: Use `display-mode: standalone` (and `navigator.standalone` on iOS) to detect installed state.

## Checklist

- [ ] Use `useInstallPrompt()` for install state; do not duplicate `beforeinstallprompt` logic.
- [ ] Keep `<InstallPrompt />` in the app shell only (renders an AlertDialog).
- [ ] Manual install button only when `canPrompt && !isInstalled`.
- [ ] Handle `promptInstall()` async (loading state, toast on outcome if desired).
- [ ] Do not log or store credentials; install analytics (if any) should be non-identifying.

## Key Files

| File | Purpose |
|------|---------|
| `frontend/lib/use-install-prompt.ts` | Hook: deferred prompt, visit count, dismissal, promptInstall, dismissBanner |
| `frontend/components/install-prompt.tsx` | AlertDialog component (used in AppShell) |
| `frontend/components/app-shell.tsx` | Renders InstallPrompt |
| `frontend/app/(dashboard)/user/preferences/page.tsx` | "Install App" card |

## References

- [PWA roadmap Phase 4](../../plans/pwa-roadmap.md#phase-4-install-experience--complete)
- [Pattern: PWA Install Prompt](../patterns/ui-patterns.md)
- [Context loading: PWA Work](../context-loading.md#pwa-work)

### Implementation Journal

- [PWA Phase 4-5 Install and Advanced (2026-01-31)](../../journal/2026-01-31-pwa-phase-4-5-install-and-advanced.md)
