# ADR-003: SSO Provider Integration

## Status

Accepted

## Date

2026-01-24

## Context

Enterprise users expect Single Sign-On (SSO) capabilities. Self-hosted users often want to log in with their existing Google, GitHub, or other accounts. We need to support multiple OAuth2/OIDC providers while keeping the system flexible and maintainable.

## Decision

We will use **Laravel Socialite** for SSO integration with a provider abstraction layer.

### Supported Providers

| Provider | Protocol | Vision Support |
|----------|----------|----------------|
| Google | OAuth2 + OIDC | ✅ |
| GitHub | OAuth2 | ✅ |
| Microsoft | OAuth2 + OIDC | ✅ |
| Apple | OAuth2 + OIDC | ✅ |
| Discord | OAuth2 | ✅ |
| GitLab | OAuth2 + OIDC | ✅ |
| Generic OIDC | OIDC | ✅ (Enterprise) |

### Architecture

```
┌────────────────────────────────────────────────────────────┐
│                      SSO Flow                               │
├────────────────────────────────────────────────────────────┤
│                                                             │
│  Frontend                    Backend                        │
│  ────────                    ───────                        │
│  Click "Login with Google"                                  │
│         │                                                   │
│         ▼                                                   │
│  GET /api/sso/{provider}/redirect                          │
│         │                                                   │
│         ▼                                                   │
│  Redirect to Google ──────────► Google OAuth                │
│                                     │                       │
│                                     ▼                       │
│  ◄─────────────────────────── Callback with code           │
│         │                                                   │
│         ▼                                                   │
│  GET /api/sso/{provider}/callback                          │
│         │                                                   │
│         ▼                                                   │
│  Exchange code for tokens                                   │
│  Fetch user info                                            │
│  Find or create user                                        │
│  Link social account                                        │
│  Create session                                             │
│         │                                                   │
│         ▼                                                   │
│  Redirect to dashboard                                      │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

### Account Linking

When a user logs in via SSO:

1. If social account exists → Log in to linked user
2. If user is logged in → Link social account to current user
3. If email matches existing user → Prompt to link or create new
4. Otherwise → Create new user account

### Database Schema

```sql
social_accounts
├── id
├── user_id (FK → users)
├── provider (google, github, etc.)
├── provider_id (unique ID from provider)
├── token (encrypted access token)
├── refresh_token (encrypted)
├── expires_at
├── avatar_url
└── created_at / updated_at

UNIQUE(provider, provider_id)
```

### Configuration

Providers are configured via environment variables:

```env
# Google
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_ENABLED=true

# GitHub
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_ENABLED=true

# Generic OIDC (Enterprise)
OIDC_CLIENT_ID=
OIDC_CLIENT_SECRET=
OIDC_ISSUER_URL=
OIDC_ENABLED=false
```

### Provider Enable/Disable

Providers can be enabled/disabled via:
1. Environment variables (default)
2. Admin settings UI
3. Database settings table

The frontend dynamically shows only enabled providers.

## Consequences

### Positive

- Laravel Socialite is well-tested with many providers
- Easy to add new providers (community packages available)
- Consistent OAuth2 flow across providers
- Account linking prevents duplicate accounts
- Generic OIDC supports enterprise IdPs (Okta, Auth0, Keycloak)

### Negative

- Each provider requires app registration and configuration
- Token refresh handling varies by provider
- Some providers have limited scopes (GitHub no phone)
- Enterprise OIDC requires additional setup

### Neutral

- Social accounts are optional (can disable all SSO)
- Users can have multiple social accounts linked
- Avatar URLs from providers may expire

## Related Decisions

- [ADR-002: Authentication Architecture](./002-authentication-architecture.md)
- [ADR-004: Two-Factor Authentication](./004-two-factor-authentication.md)

## API Documentation

- [Laravel Socialite](https://laravel.com/docs/12.x/socialite)
- [Google OAuth2](https://developers.google.com/identity/protocols/oauth2)
- [GitHub OAuth Apps](https://docs.github.com/en/apps/oauth-apps)
- [Microsoft Entra ID OAuth2](https://learn.microsoft.com/en-us/entra/architecture/auth-oauth2)
- [Apple Sign In](https://developer.apple.com/documentation/signinwithapple)
- [Discord OAuth2](https://docs.discord.com/developers/topics/oauth2)
- [GitLab OAuth2](https://docs.gitlab.com/api/oauth2/)

## Notes

For generic OIDC provider (enterprise IdPs), we use the `socialiteproviders/oidc` package which supports:
- Auto-discovery via `.well-known/openid-configuration`
- Standard OIDC claims (sub, email, name, picture)
- Custom claim mapping for non-standard IdPs

## Implementation Journal

- [SSO Settings Migration (2026-01-28)](../journal/2026-01-28-sso-settings-migration.md)
- [SSO Settings Enhancement (2026-01-30)](../journal/2026-01-30-sso-settings-enhancement.md)
- [SSO Test Toggle Fix (2026-02-05)](../journal/2026-02-05-sso-test-toggle-fix.md)
- [SSO Redirect URI Fix (2026-02-05)](../journal/2026-02-05-sso-redirect-uri-fix.md)
- [SSO Callback Page Fix (2026-02-06)](../journal/2026-02-06-sso-callback-page-fix.md)
- [SSO Session Persistence Fix (2026-02-06)](../journal/2026-02-06-sso-session-persistence-fix.md)
