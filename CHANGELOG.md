# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [0.10.4] - 2026-03-10

### Fixed
- Fix `queue:monitor` scheduled command missing required `queues` argument, causing errors every 5 minutes

## [0.10.3] - 2026-03-10

### Added
- AI-readable changelog export with version range picker and downloadable markdown upgrade guide
- Changelog export API endpoints (`GET /changelog/versions`, `GET /changelog/export`) with rate limiting
- Admin-configurable export settings for format, detail level, and instruction style
- Notification permission guided flow in onboarding wizard (requests browser push directly)
- Notification permission banner component for preferences page
- Contextual notification prompt hook (`useNotificationPrompt`) triggered after key actions

### Changed
- Release script now requires manual changelog entries instead of auto-generating from commits
- Backfilled detailed changelog entries for v0.10.2
- Updated changelog help content with AI export documentation
- Added AI export keywords to changelog search page entry

### Fixed
- Fix avatar image stretching in profile, user table, and user dropdown with `object-cover`

## [0.10.2] - 2026-03-09

### Added
- Backup upload endpoint (`POST /api/backup/upload`) for storing backup files without restoring
- "Queued" notification delivery status with stats card in delivery log
- Changelog entries pattern documentation and get-cooking audit roadmap

### Changed
- LLM model discovery falls back to stored provider credentials when editing (no need to re-enter API key)
- AI provider dialog allows test/discover without re-entering credentials for existing providers
- Remove password re-confirmation from 2FA disable, recovery codes, and regenerate endpoints
- Release script skips auto-changelog generation when manual entry already exists
- Queue worker explicitly specifies `database` connection in supervisord
- Breadcrumb "user" segment rendered as non-navigable

### Fixed
- Fix 2FA TOTP verification by trimming padding from secret before validation
- Fix avatar image stretching with `object-cover` styling
- Fix changelog page list styling to use proper disc markers

## [0.10.1] - 2026-03-08

### Added
- Dashboard redesign with modular widget components: system health, recent activity, notifications, storage overview, upcoming tasks, and environment info
- Reusable DashboardSection wrapper component for consistent dashboard layout
- Dashboard API endpoint for aggregated widget data
- Notification configuration extracted into dedicated tab components: channels, delivery log, email, Novu, and templates
- AI provider dialog decomposed into ProviderCredentialFields and ProviderModelSelection sub-components with model caching
- Two-factor authentication service improvements

### Changed
- Redesign dashboard page from monolithic layout to modular widget-based architecture
- Refactor notification configuration: extract ~2,600 lines across 5 tab components
- Refactor AI provider dialog: extract credential fields and model selection with response caching
- Refactor email configuration, email templates, notification deliveries, and Novu pages into leaner compositions
- Improve WebPush channel delivery logic
- Improve avatar upload component
- Update Docker entrypoint with improved initialization

## [0.10.0] - 2026-03-08

### Added
- Avatar upload component for user profiles
- AI provider management dialog with full CRUD for LLM provider configuration
- SSO configuration decomposed into provider cards, OIDC card, and global options card
- Security overview dashboard on the user security page
- Reusable DataTable component for consistent table rendering
- Help center article table of contents with scroll-spy navigation
- Profile API endpoint for user profile management
- OWASP ASVS security audit roadmap and design review roadmap

### Changed
- Refactor AI configuration page: extract type definitions, provider cards, orchestration mode cards, and provider list into dedicated components (page reduced from ~1,400 to ~200 lines)
- Refactor SSO configuration page into sso-provider-card, sso-oidc-card, sso-global-options-card, and shared types
- Refactor admin user table into smaller, maintainable pieces
- Redesign about dialog, sidebar navigation, user dropdown menu, and auth page layout
- Redesign notification list items and notification bell/dropdown
- Expand user preferences page with additional settings
- Rewrite OpenAPI specification with expanded endpoint documentation

## [0.9.2] - 2026-03-05

### Fixed
- Align local push script with CI checks (lint, build, audit)

## [0.9.1] - 2026-03-05

### Fixed
- Fix TypeScript type error breaking CI build
- Fix composer audit failure from abandoned package

## [0.9.0] - 2026-03-05

### Added
- API audit with consistent validation across all controllers (form requests, authorization checks)
- DeprecateRoute middleware for RFC 8594 route deprecation headers
- 9 new backend test suites: AccessLog, FileManager, Job, MailSetting, Profile, Setting, SystemSetting, User, Webhook controllers
- 4 new frontend test suites: sanitize, use-permission, utils, validation-schemas
- FileHelper and QueryHelper utility classes
- use-debounce hook for input field optimization
- 4 new ADRs: Real-Time Streaming (027), Webhook System (028), Usage Tracking (029), File Manager (030)
- 7 new recipes: changelog entry, file manager, passkey, real-time streaming, usage tracking, webhook, onboarding
- 6 new patterns: API key service, auth middleware, real-time streaming, sanitize, security, webhook service
- UserService with deleteUser() for proper cascade cleanup
- Dependabot configuration for automated dependency updates
- Bug tracker for cross-cutting issue tracking

### Changed
- Refactor all API controllers with consistent error handling and validation
- Consolidate notification settings into NotificationController (remove separate NotificationSettingsController)
- Rework logs configuration page with improved filtering
- Split monolithic patterns.md (2,643 lines) and anti-patterns.md (1,049 lines) into individual focused documents
- Expand Docker entrypoint with improved initialization logic
- Update Nginx security headers configuration

### Security
- Encrypt webhook secrets at rest with migration for existing secrets
- Add input sanitization with expanded XSS prevention
- Tighten rate limiting on sensitive endpoints
- Add Dependabot for automated security patch monitoring

### Removed
- Remove NotificationSettingsController (consolidated into NotificationController)

## [0.8.6] - 2026-03-03

### Fixed
- Help center code block overflow causing horizontal scroll
- Download button not working in help center modal

## [0.8.5] - 2026-03-03

### Added
- Standalone documentation site for public-facing project docs
- socket.io-client dependency for improved real-time connectivity

### Changed
- Replace all branding assets with new design (favicon, PWA icons in all sizes, apple-touch-icon)
- Update logo component with new SVG design
- Rewrite README with updated project description and setup instructions
- Update help article rendering with improved typography

## [0.8.4] - 2026-03-02

### Added
- Setup verification script (verify-setup.sh) for validating project configuration
- Recipe for updating AI configs and documentation after project customization
- Pre-flight validation in release workflow

### Changed
- Improve onboarding recipes (get-cooking, setup-features-auth, setup-new-project) with expanded guidance
- Expand release script (push.ps1) with additional checks

### Fixed
- Fix WebSocket upgrade headers to be conditional — prevents errors when Reverb is not configured
- Fix help center download button and modal edge cases

## [0.8.3] - 2026-03-01

### Added
- Comprehensive GraphQL API documentation in help center (1,200+ lines of content)
- Download Docs button in help center for offline reference
- Backend search pages registry (search-pages.php) for server-side page indexing
- Frontend search pages registry (search-pages.ts)
- GraphQL configuration page placeholder

### Changed
- Refactor UserSettingController with improved validation and error handling

### Security
- Sanitize search result highlights with DOMPurify to prevent stored XSS
- Pin rollup >= 4.59.0 to patch CVE-2026-27606
- Change GraphQL CORS default from wildcard (*) to app frontend URL

## [0.8.2] - 2026-02-28

### Changed
- Refine theme picker with improved UX and layout
- Simplify theme provider architecture (centralize theme config in app-config)
- Update theme toggle with cleaner mode switching
- Add user setting validation in UserSettingController

## [0.8.1] - 2026-02-28

### Changed
- Restrict color theme picker to admin-only Configuration > Branding page
- Simplify user preferences to show light/dark/system mode toggle only
- Update onboarding wizard to show mode selection instead of color theme grid

## [0.8.0] - 2026-02-28

### Added
- Inline migration seeders for email and notification templates (no separate seeder step needed)
- Database audit index cleanup migration for improved query performance

### Changed
- Switch to Laravel Reverb defaults for WebSocket broadcasting (removes manual Pusher/broadcasting config)
- Simplify theme selection in user preferences and onboarding
- Update Docker image with additional build dependencies

### Removed
- Remove standalone broadcasting.php config file (Reverb uses framework defaults)

## [0.7.11] - 2026-02-27

### Added
- Theming engine with 18 color themes (Amber, Bubblegum, Catppuccin, Coffee, Cyberpunk, Dracula, Forest, Lavender, Midnight, Mono, Nord, Ocean, Rose, Sakura, Slate, Solarized, Sunset, and Default)
- Theme picker component with live preview
- Breadcrumb navigation component across all pages
- WebAuthn certificate attestation support for passkeys

### Changed
- Redesign auth pages (login, forgot password, reset password) with updated layout and styling
- Restyle dashboard widgets (stats, welcome, quick actions, usage) with theme-aware colors
- Refactor real-time streaming hooks (Echo, audit stream, app log stream) for reliability

## [0.7.10] - 2026-02-27

### Fixed
- Fix email verification page firing verification request twice
- Add user_id and disabled_at columns to webauthn_credentials table for proper user scoping
- Fix Echo config missing Pusher cluster field

## [0.7.8] - 2026-02-26

### Changed
- Refactor notification controller for improved reliability
- Update Docker configuration

### Fixed
- Fix passkey error handling for invalid credentials
- Fix push notification device matching across multiple devices
- Fix WebAuthn permissions policy header

## [0.7.6] - 2026-02-26

### Added
- Push notification diagnostics endpoint for troubleshooting delivery issues
- Delivery debug logging for push notification channel

## [0.7.5] - 2026-02-26

### Fixed
- Always show native push notification regardless of window focus state

## [0.7.4] - 2026-02-26

### Fixed
- Separate webpush channel toggle from device management to prevent accidental unsubscription

## [0.7.3] - 2026-02-26

### Fixed
- Improve PWA push notification reliability across multiple devices
- Fix multi-device subscription management UX

## [0.7.2] - 2026-02-26

### Changed
- Extract business logic from controllers into dedicated service classes
- Add error boundary pages for auth, dashboard, and root layouts
- Extract storage, AI, and security page components into reusable modules
- Add new services: AuthService, SSOSettingService, SSOTestService, WebhookService, NotificationTemplateSampleService

## [0.7.1] - 2026-02-25

### Fixed
- Improve multi-device push subscriptions and service worker updates

## [0.7.0] - 2026-02-24

### Added
- Laravel Reverb WebSocket support for real-time broadcasting
- Multi-device push subscription management
- Passkey authentication improvements

### Fixed
- Exclude missing-user-entrypoint Semgrep rule variant

## [0.6.4] - 2026-02-24

### Fixed
- Migrate Semgrep from GitHub Action to direct CLI with rule exclusions for cleaner CI
- Fix flaky GraphQL error test

## [0.6.3] - 2026-02-24

### Fixed
- Resolve Semgrep CI findings with nosemgrep suppressions for false positives
- Add shared security headers Nginx include file

## [0.6.2] - 2026-02-24

### Security
- SSRF protection hardening with DNS pinning to prevent DNS rebinding attacks
- Internal error details no longer leak to API responses
- Add security headers to Nginx configuration

## [0.6.1] - 2026-02-24

### Changed
- Migrate passkey authentication from custom PasskeyService to Laragear WebAuthn typed request classes
- Simplify PasskeyController register/login flows using built-in request methods
- Add Auth::logout() and session invalidation when disabled user attempts passkey login
- Update User model and auth config for WebAuthn compatibility

## [0.6.0] - 2026-02-23

### Added
- GraphQL introspection enabled for development tooling
- Release test gates — push.ps1 now runs backend and frontend tests before releasing
- Passkeys code review task added to roadmap

### Fixed
- Register Lighthouse service provider in bootstrap/providers.php for Laravel 11 GraphQL routes
- Remove incorrect @field directives from GraphQL schema to allow Lighthouse auto-discovery
- Fix RefreshDatabase transaction isolation so test data is visible to GraphQL HTTP requests
- Add context-based user resolution for GraphQL resolvers with auth guard fallback
- Fix DisableIntrospection to use int constants with explicit feature gate in tests

## [0.5.2] - 2026-02-22

### Fixed
- Correct Lighthouse error handlers and Stripe webhook test expectations

## [0.5.1] - 2026-02-22

### Fixed
- Resolve CI test failures in Stripe webhook, GraphQL, and API key tests

## [0.5.0] - 2026-02-22

### Added
- Stripe Connect integration with 1% application fee, Connect onboarding, webhooks, payment history, and settings UI
- GraphQL API via Lighthouse with queries and mutations for notifications, profile, and settings
- Notification delivery tracking across all notification channels
- API key management — create, revoke, and manage API keys for programmatic access
- PWA improvements for enhanced progressive web app experience

## [0.4.0] - 2026-02-15

### Added
- Notification system overhaul with per-user settings, timezone support, and channel management
- Push subscription expiry detection that auto-removes stale subscriptions

### Changed
- Replace hand-rolled RFC 8291 WebPush payload encryption with minishlink/web-push library
- Release tooling — push.ps1 now auto-detects branch, guards against detached HEAD, supports non-interactive mode

### Fixed
- Fix TypeScript error in notifications page by properly casting Object.values() result
- Update composer.lock to include minishlink/web-push dependencies

## [0.3.1] - 2026-02-15

### Added
- Service worker cache versioning — release pipeline auto-updates CACHE_VERSION in sw.js
- Service worker cleans up old versioned caches on activate
- Expand add-searchable-model recipe with dedicated search methods, validation, and Scout config

## [0.3.0] - 2026-02-16

### Fixed
- SystemSetting model returned string "null" instead of PHP null when settings were cleared, causing broken images in branding after logo deletion
- Changelog page empty in Docker — CHANGELOG.md was not copied into Docker image or volume-mounted for development

### Changed
- SystemSetting value getter now uses json_last_error() instead of null-coalescing operator for correct null handling
- Frontend branding settings and app-config provider sanitize the string "null" as defense-in-depth

## [0.2.0] - 2026-02-15

### Added
- Integration Usage Dashboard with cost tracking across LLM, Email, SMS, Storage, and Broadcasting providers
- Usage stats API with date range, integration, and provider filters
- Stacked area chart for cost trends and sortable provider breakdown table
- Cost alert budgets with daily scheduled checks and admin notifications
- CSV export of filtered usage data
- Monthly cost dashboard widget with sparkline trend
- Per-user cost attribution for LLM and SMS integrations
- "Get Cooking" tiered setup wizard for new project customization
- Changelog page in Configuration area for viewing version history

### Changed
- Dark mode fixes across configuration pages for consistent theme adherence

### Fixed
- Fix theme preference race condition — use localStorage as single source of truth instead of stale API values

## [0.1.26] - 2026-02-14

### Added
- Integration Usage Dashboard (Configuration > Usage & Costs) with cost tracking across LLM, Email, SMS, Storage, and Broadcasting
- Usage tracking instrumentation in LLM orchestrator, email/SMS channels, and storage service
- Usage stats API with date range, integration, and provider filters
- Stacked area chart for cost trends and sortable provider breakdown table
- Cost alert budgets with daily scheduled checks and admin notifications
- CSV export of filtered usage data
- Monthly cost dashboard widget with sparkline trend for admin dashboard
- Per-user cost attribution for LLM and SMS integrations
- "Get Cooking" tiered setup wizard for new project customization (3-tier guided flow)
- Changelog page in Configuration area for viewing version history
- Dark mode fixes across configuration pages for consistent theme adherence

## [0.1.25] - 2026-02-07

### Added
- Novu notification infrastructure integration (optional cloud/self-hosted)
- Local notification system remains as default fallback

### Changed
- Notification system refactored to support Novu as optional provider

## [0.1.24] - 2026-02-06

### Added
- PWA configuration navigation on mobile devices
- Faster sign-out flow with immediate UI feedback

### Fixed
- Mobile navigation in PWA standalone mode

## [0.1.23] - 2026-02-05

### Added
- In-app documentation and help center with searchable articles
- Setup wizard for first-time onboarding
- Security compliance documentation (SOC 2, ISO 27001 templates)
- GitHub Actions CI/CD hardening

### Fixed
- Docker build optimization and security updates
- Meilisearch production permission denied errors
- Cache permissions in container
- SSO test connection toggle state
- Page titles now use configured app name
- PWA service worker hardening and offline improvements

### Changed
- Documentation restructured for better developer experience
- Login flow reviewed and tested end-to-end
- Docker container security audit completed

## [0.1.22] - 2026-02-04

### Fixed
- Security page architecture cleanup

## [0.1.21] - 2026-02-02

### Added
- SAST (Static Application Security Testing) automation
- Security headers and CORS hardening

## [0.1.20] - 2026-01-31

### Added
- PWA offline experience with background sync
- PWA push notifications via Web Push (VAPID)
- PWA install experience with custom prompts
- Documentation audit across all docs (8 phases)

## [0.1.19] - 2026-01-30

### Added
- Meilisearch integration (embedded in container, full-text search)
- Meilisearch admin configuration page
- User groups with permission-based access control
- Configurable auth features (registration, email verification, password reset)
- Storage settings with multiple provider support (S3, GCS, Azure, DO Spaces, MinIO, B2)
- Storage analytics and monitoring
- SSO settings enhancement with per-provider configuration
- Dashboard static simplification

### Changed
- Admin status now determined by group membership (removed is_admin column)
- Migrated from Alpine to Debian for Meilisearch compatibility
- Notification templates implementation

## [0.1.18] - 2026-01-29

### Added
- Configuration navigation redesign with grouped collapsible sections
- Live console logs and HIPAA access logging
- Audit dashboard analytics with charts and statistics
- Real-time audit log streaming
- LLM model discovery (test key, fetch models per provider)
- User management admin interface
- Email template system with editor and preview
- Branded iconography across the application

### Changed
- LLM settings consolidated into single AI configuration page
- Collapsible settings UI pattern standardized

## [0.1.17] - 2026-01-28

### Added
- SSO settings migration to database (env to DB Phase 5)
- Notification and LLM settings migration (env to DB Phases 3-4)
- SettingService implementation with env fallback and encryption (Phases 1-2)
- Notification configuration split (global vs per-user)

## [0.1.16] - 2026-01-27

### Added
- Multi-channel notification system (email, SMS, push, in-app, chat)
- Mobile-responsive design across all pages
- shadcn/ui CLI migration for component management
- Branding and UI consistency improvements
- Settings page restructure
- Critical bug fixes

### Changed
- Navigation refactored for mobile-first approach
