# Faster Sign Out - 2026-02-06

## Overview

Completed the Faster Sign Out roadmap item: added an "Are you sure?" confirmation dialog before sign-out, then implemented optimistic logout for instant UI transition (clear state and redirect immediately; backend API call fire-and-forget).

## Implementation

### 1. AlertDialog component

- Added `@radix-ui/react-alert-dialog` to `frontend/package.json`
- Created `frontend/components/ui/alert-dialog.tsx` — shadcn-style AlertDialog with Overlay, Content, Header, Footer, Title, Description, Action, Cancel. Uses `buttonVariants` for styling.

### 2. User dropdown changes

- **Confirmation flow:** Clicking "Sign Out" in the user dropdown opens an AlertDialog with "Sign out?" title and "Are you sure you want to sign out of your account?" description. Cancel and Sign Out (destructive) buttons.
- **Optimistic logout:** On confirm, `handleLogout`:
  1. Immediately clears Zustand state: `useAuth.setState({ user: null, error: null })`
  2. Redirects to `/login` via `router.push("/login")`
  3. Fires `api.post("/auth/logout")` in the background (fire-and-forget with `.catch(() => {})`)

### 3. No backend changes

- `AuthController::logout()` unchanged. The frontend no longer blocks on the API response.

## Files Modified

- `frontend/package.json` — added `@radix-ui/react-alert-dialog`
- `frontend/components/ui/alert-dialog.tsx` — new
- `frontend/components/user-dropdown.tsx` — confirmation dialog + optimistic logout

## Notes

- Run `npm install` in the frontend directory to install the new dependency (e.g. in Docker or Linux if Windows npm has platform issues).
- Session invalidation still happens on the backend; the user is redirected before the response returns, giving instant perceived logout.
