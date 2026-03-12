# Wizard and Help Center Styling Fixes - 2026-02-05

## Overview

Fixed styling, accessibility, and UX issues in the onboarding wizard and help center modals. The most visible problem was a double close (X) button on both modals; additional fixes addressed overflow, footer balance, completion-step navigation, conditional security step, and responsive behavior.

## Implementation Approach

- **DialogContent**: Added optional `hideClose` prop so modals with custom close buttons can suppress the built-in Radix close button.
- **Wizard**: Applied `hideClose`, added visually hidden `DialogDescription`, scrollable content area, hidden nav row on last step, and dynamic step list that excludes the security step when both 2FA and passkeys are disabled.
- **Help Center**: Applied `hideClose`, `DialogDescription`, responsive height (`h-[80vh] max-h-[600px]`), and sidebar hidden below `md` so mobile uses the category grid only.
- **Completion step**: Replaced `<Link>` with buttons that call `onComplete()` then `router.push()` so the wizard is marked complete before navigation.

## Challenges Encountered

- None; changes were straightforward.

## Key Files

- `frontend/components/ui/dialog.tsx` — `hideClose` prop
- `frontend/components/onboarding/wizard-modal.tsx` — wizard fixes (hideClose, description, scroll, footer, filtered steps)
- `frontend/components/onboarding/steps/completion-step.tsx` — complete-then-navigate links
- `frontend/components/help/help-center-modal.tsx` — help center fixes (hideClose, description, height, sidebar)

## Testing Notes

- Verify wizard shows a single X button; no duplicate close.
- Verify help center shows a single X button.
- On completion step, click "Go to Dashboard" or "Edit Profile" and confirm wizard does not reappear on next load.
- When both 2FA and passkeys are disabled in app config, security step should not appear in the wizard.
- Wizard content (e.g. Tour step) scrolls when content exceeds height; help center fits short viewports with 80vh max-h.
