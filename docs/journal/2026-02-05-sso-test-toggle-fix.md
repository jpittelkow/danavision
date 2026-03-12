# SSO Test Connection Toggle Fix - 2026-02-05

## Overview

Fixed a bug where successfully testing an SSO provider's credentials did not visibly enable the provider toggle. The test showed a success toast but the enable toggle either stayed hidden (card collapsed) or appeared in the OFF position.

## Root Cause

After a successful connection test, `testConnection()` called `fetchSettings()` which:

1. Set `isLoading = true`, unmounting the entire form and showing a skeleton
2. Refetched all settings from the API
3. Called `reset(formValues)` to reinitialize the form
4. All `CollapsibleCard` components remounted with `defaultOpen={false}`, collapsing the card the user was working in

The enable toggle appeared inside the now-collapsed card, and was in the OFF position (because the backend had forced `{provider}_enabled = false` during the initial credential save, since `test_passed` was not yet set at that time).

## Fix

Replaced the `fetchSettings()` call in `testConnection` with direct form updates:

- `setValue(testPassedKey, true)` — makes the enable toggle appear immediately
- `setValue(enabledKey, true, { shouldDirty: true })` — flips the toggle ON and marks the form dirty so the Save button activates
- `setOriginalValues(...)` — marks `test_passed` as the new baseline (not dirty)

This keeps the card open, avoids a full-page reload, and gives the user the expected experience: test succeeds → toggle appears and is ON → click Save.

## Key Files

- `frontend/app/(dashboard)/configuration/sso/page.tsx` (testConnection function)

## Testing Notes

- Test with each provider type (OAuth providers and OIDC)
- Verify the card stays open after a successful test
- Verify the enable toggle appears and is ON
- Verify the Save button becomes active
- Verify saving successfully persists the enabled state
- Verify failed tests still show error toast without changing toggle state
