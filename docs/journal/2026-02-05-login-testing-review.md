# Login Testing & Review - 2026-02-05

## Overview

Completed the Login Testing & Review roadmap item: code review of auth controllers and frontend pages, security verification, manual testing checklist, new backend and E2E tests, and documentation.

## Implementation Approach

### Phase 1: Code Review

- **Backend**: Reviewed AuthController, TwoFactorController, PasskeyController, SSOController. No debug code; validation and audit logging present. One gap: SSO callback did not log auth events.
- **Fix**: Injected AuditService into SSOController and added `logAuth('sso_login', $result['user'], ['provider' => $provider])` on successful callback.
- **Frontend**: Reviewed login, register, forgot-password, reset-password, verify-email pages. Forms use zod validation, loading states, and accessible markup. No hardcoded credentials.

### Phase 2: Security Review

- **Rate limiting**: Confirmed in `backend/routes/api.php`: login, register, forgot-password, reset-password use `rate.sensitive:*`; check-email and passkey login use `throttle:10,1`; 2FA verify uses `rate.sensitive:2fa`.
- **User enumeration**: Login returns "Invalid credentials" for both wrong password and non-existent email. Password reset always returns the same success message.
- **Session**: Session regenerated on login; invalidated and token regenerated on logout. Config uses `http_only`, `same_site`, and env-driven `secure`.

### Phase 3 & 4: Manual Testing and Edge Cases

- Added a reusable checklist at `docs/journal/login-testing-review-checklist.md` for manual verification of login, registration, password reset, email verification, 2FA, passkeys, SSO, and session behavior.
- Edge cases (config combinations, SSO errors, disabled accounts) are covered by the checklist and by new automated tests where applicable.

### Phase 5: Test Coverage

**Backend (AuthTest.php):**

- Disabled account login rejection (403 with disabled message).
- Password reset: 503 when email not configured (mocked EmailConfigService); 200 when configured; reset with valid token (Password::broker()->createToken); 400 for invalid token.
- Email verification: valid id/hash verifies; invalid hash returns 400; already-verified returns success.
- 2FA required mode: when SettingService returns `two_factor_mode` = required and user has no 2FA, GET /api/auth/user returns 403 with `requires_2fa_setup: true`.

**E2E (auth.spec.ts):**

- Successful registration: fill register form with unique email, submit, expect redirect to `/dashboard`.
- Successful login: register user, open user dropdown, Sign Out, then login with same credentials, expect `/dashboard`.

## Challenges Encountered

- E2E tests depend on a running app and backend; Playwright was not run in this session (PHP/Node not in path in the environment). Tests are written for use in CI or local Docker.
- Password reset tests require mocking EmailConfigService for the 503 and 200 cases; Laravel's Password broker is used as-is for token creation in the reset test.

## Observations

- Auth flows are consistent with ADR-002, ADR-004, and ADR-018. No ADR updates were required.
- SSO audit logging was the only code change from the review; the rest of the auth stack met the review criteria.

## Trade-offs

- Manual testing is left to the checklist; full E2E runs require a running stack (e.g. Docker).
- Rate limiting for verify-email and resend-verification was not added; resend is behind auth and verify is link-based. Can be revisited if abuse is observed.

## Next Steps (Future Considerations)

- Run full E2E auth suite in CI or Docker and fix any environment-specific failures.
- Optionally add throttle to POST /auth/verify-email to limit id/hash guessing.
- Complete manual checklist and record results in a test report if desired.

## Testing Notes

- Run backend auth tests: `php artisan test tests/Feature/AuthTest.php tests/Feature/TwoFactorTest.php`
- Run E2E auth tests: from project root with app and backend up, `npm run test:e2e` (or frontend `test:e2e`) for `e2e/auth.spec.ts`
- Use `docs/journal/login-testing-review-checklist.md` for manual sign-off of auth flows.
