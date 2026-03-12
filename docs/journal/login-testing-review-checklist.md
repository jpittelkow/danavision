# Login Testing & Review - Manual Testing Checklist

Use this checklist when performing manual testing of auth flows. Check off each item as completed.

## Login Flows

- [ ] Valid email/password login → redirects to dashboard
- [ ] Invalid password → "Invalid credentials" (no user enumeration)
- [ ] Non-existent email → same "Invalid credentials" message
- [ ] Disabled account → "This account has been disabled" (403)
- [ ] Remember me checked → session persists after closing browser
- [ ] SSO: Click provider when configured → redirects to provider, returns authenticated
- [ ] SSO: No providers configured → SSO buttons hidden
- [ ] Passkey: Click when enabled and supported → WebAuthn flow, then authenticated
- [ ] Passkey: Browser unsupported → passkey button hidden
- [ ] 2FA user: After password → 2FA form shown; valid code → dashboard
- [ ] 2FA user: Invalid code → error, can retry
- [ ] 2FA: Cancel → returns to login form

## Registration

- [ ] Valid registration → account created, logged in, redirect to dashboard
- [ ] First user → gets admin group
- [ ] Duplicate email → validation error
- [ ] Weak password → strength indicator / validation error
- [ ] Password mismatch → "Passwords don't match"
- [ ] Email availability → real-time feedback when email taken

## Password Reset

- [ ] Forgot password when enabled → submit email → success message (same for unknown email)
- [ ] Password reset disabled → Forgot password link hidden; direct POST returns 503
- [ ] Valid reset link → set new password → success
- [ ] Expired/invalid token → error message
- [ ] New password fails policy → validation error

## Email Verification

- [ ] Required mode → unverified user cannot access app
- [ ] Optional mode → can access, reminder shown
- [ ] Resend verification → email sent (rate limited if applicable)
- [ ] Valid verification link → email verified

## Session

- [ ] Logout → session cleared, redirect to login
- [ ] Visit /dashboard unauthenticated → redirect to login
- [ ] Session regeneration on login/logout (check session cookie change if possible)

## Edge Cases

- [ ] 2FA required by admin, user without 2FA → redirected to set up 2FA
- [ ] Passkey required, no WebAuthn → appropriate message
- [ ] SSO callback with invalid state → error, no login
- [ ] Forgot password when email not configured → 503 with clear message
