# Roadmaps & Plans

Development roadmaps and implementation history.

## Bug Tracker

Persistent log of suspected bugs and issues to investigate. Always kept up to date.

**[Bug Tracker](plans/bug-tracker.md)**

## Active Development

Currently in progress. Complete these before starting new work.

_(None — all items archived. Pick from Planned Features below or add new items.)_

## Next Up

Ready to start. These are unblocked and can begin immediately.

_(None — pick from Planned Features below or add new items.)_

## Planned Features

Requires foundation work or longer-term planning.

- [ ] **OWASP ASVS Level 2 Audit** - Structured security audit against OWASP Application Security Verification Standard v4.0.3. 198 items across 14 verification categories. ~72% pass rate with 54 gaps identified and prioritized. (see [OWASP ASVS Audit](plans/owasp-asvs-audit-roadmap.md))
- [x] **Re-evaluate Stripe Integration** - ~~Review the current Stripe Connect integration.~~ Complete — removed Stripe Connect, platform fees, and commercial license. Simplified to plain Stripe with feature-gating. Fully MIT. See [ADR-026](adr/026-stripe-integration.md).
- [ ] **Documentation Review** - Comprehensive review of all project documentation for accuracy, completeness, and consistency. Audit ADRs, recipes, patterns, and guides against current codebase. Identify stale references, missing docs for new features, broken links, and gaps in onboarding material. Standardize formatting and cross-references across all doc files. (see [Documentation Review Roadmap](plans/documentation-review-roadmap.md))
- [ ] **Get Cooking Script Audit** - Re-audit the "Get Cooking" onboarding script (`setup-new-project.md`) against current codebase. Previous audit (2026-03-02) scored 8/10 with 16 issues across 9 sections. Verify prior fixes were applied, identify new drift from recent features, and test the full onboarding flow end-to-end. (see [Get Cooking Audit Roadmap](plans/get-cooking-audit-roadmap.md))
- [x] **AI-Readable Changelog Export** - ~~Add a downloadable markdown export of the changelog optimized for AI agents.~~ Complete — core export (service, API, UI, tests) plus admin-configurable settings for format, detail level, and instruction style. See [journal entry](journal/2026-03-10-ai-changelog-export-settings.md).

## Release Checklist

Complete these tasks before each release:

- [x] **Documentation Architecture Review** - Fix cross-document inconsistencies, add architectural clarity, improve developer experience docs (see [Documentation Architecture Review](plans/documentation-architecture-review-roadmap.md))
- [x] **Code Review Remediation (Phase 5)** - Remaining test coverage expansion (see [Code Review Remediation](plans/code-review-remediation-roadmap.md))
- [x] **Final Code Review** - Review all modified files for bugs, debug code, hardcoded values, and adherence to patterns (see [Code Review recipe](ai/recipes/code-review.md))
- [x] **Roadmap Cleanup** - Archive completed roadmaps, verify all links work, update stale entries (see Roadmap Maintenance below)
- [ ] **User Build Verification** - Manually verify the Docker build works end-to-end (see Build Verification below)

## Completed

See **[roadmap-archive.md](roadmap-archive.md)** for all completed roadmaps and journal entries.

## Integration Costs

Reference for paid third-party integrations used by Sourdough. All integrations are optional — the app runs fully self-hosted with no paid services required. Costs only apply when an admin configures and enables a paid provider.

### LLM Providers (per-token/per-request)

| Provider | Pricing Model | Notes |
|----------|--------------|-------|
| OpenAI (GPT-4, GPT-4o) | Per input/output token | Varies by model; GPT-4o is cheaper than GPT-4 |
| Anthropic (Claude) | Per input/output token | Varies by model tier (Haiku, Sonnet, Opus) |
| Google Gemini | Per input/output token | Free tier available; paid for higher usage |
| AWS Bedrock | Per input/output token | Pay-per-use via AWS account; model pricing varies |
| Azure OpenAI | Per input/output token | Azure subscription required; same models as OpenAI |
| Ollama (local) | Free (self-hosted) | Runs on local hardware; no API costs |

**Cost amplifiers:** Aggregation mode queries all configured providers (multiplied cost); Council mode queries all providers plus a synthesis step. Single mode is the most cost-efficient.

### Email Providers (per message)

| Provider | Pricing Model | Notes |
|----------|--------------|-------|
| SMTP (self-hosted) | Free | Requires own mail server |
| Mailgun | Per email (free tier available) | 100 emails/day free, then per-email |
| SendGrid | Per email (free tier available) | 100 emails/day free, then tiered plans |
| AWS SES | Per email | ~$0.10/1,000 emails; very cost-effective at scale |
| Postmark | Per email | Transactional-focused; tiered plans |

### SMS Providers (per message)

| Provider | Pricing Model | Notes |
|----------|--------------|-------|
| Twilio | Per SMS segment | Pricing varies by country; ~$0.0079/msg (US) |
| Vonage | Per SMS segment | Pricing varies by country |
| AWS SNS | Per SMS | ~$0.00645/msg (US); international rates vary |

### Storage Providers (per GB/month)

| Provider | Pricing Model | Notes |
|----------|--------------|-------|
| Local disk | Free | Default; limited by server disk |
| Amazon S3 | Per GB stored + requests | ~$0.023/GB/month (Standard) |
| Google Cloud Storage | Per GB stored + requests | ~$0.020/GB/month (Standard) |
| Azure Blob Storage | Per GB stored + requests | ~$0.018/GB/month (Hot tier) |
| DigitalOcean Spaces | Flat + per GB | $5/month includes 250 GB |
| MinIO (self-hosted) | Free | S3-compatible; runs on own infrastructure |
| Backblaze B2 | Per GB stored + requests | ~$0.006/GB/month; 10 GB free |

### Real-Time / Broadcasting

| Provider | Pricing Model | Notes |
|----------|--------------|-------|
| Reverb | Self-hosted (free) | Included in Docker container. Used for live streaming features (audit logs, app logs, notifications) |

### Notification Services (optional)

| Provider | Pricing Model | Notes |
|----------|--------------|-------|
| Novu (optional) | Free tier + usage-based | Optional notification infrastructure (Cloud or self-hosted). Free for 30k events/month. Local system remains default fallback. See [ADR-025](adr/025-novu-notification-integration.md). |

### Payment Processing (per transaction)

| Provider | Pricing Model | Notes |
|----------|--------------|-------|
| Stripe | 2.9% + 30c per transaction | Optional — only when Stripe is enabled and configured. See [ADR-026](adr/026-stripe-integration.md). |

### Free Integrations (no cost)

These integrations are self-hosted or free and incur no third-party costs:

- **Meilisearch** — Embedded in Docker container (self-hosted)
- **Ollama** — Local LLM inference (self-hosted)
- **SSO/OAuth providers** — Google, GitHub, Microsoft, Apple, Discord, GitLab authentication is free
- **Telegram, Discord, Slack, Matrix, ntfy** — Notification channels use free APIs/webhooks
- **Web Push (VAPID)** — Browser push notifications are free
- **SMTP** — Self-hosted email is free

### Cost Management Considerations

- **LLM is typically the largest cost** — Monitor token usage; prefer Single mode over Aggregation/Council for routine queries
- **Email costs are usually negligible** — Most apps send fewer than 1,000 emails/month (well within free tiers)
- **SMS is per-message** — Can add up with international recipients; consider limiting to critical notifications only
- **Storage scales with data** — Local disk is free; cloud storage costs grow with backup frequency and file uploads
- **Broadcasting is optional** — Only needed for real-time log streaming; most deployments don't require it

## Roadmap Maintenance

When adding or updating roadmaps:

1. **New roadmaps**: Add to appropriate section (Active, Next Up, or Planned) with priority
2. **Completing work**: Move to [roadmap-archive.md](roadmap-archive.md) with date, note any remaining optional work
3. **Verify links**: Ensure all roadmap file links resolve correctly
4. **Journal entries**: Add implementation notes to the Journal Entries table in [roadmap-archive.md](roadmap-archive.md)

## Build Verification

To verify the build works end-to-end:

1. Clean rebuild: `docker-compose down -v && docker-compose up -d --build`
2. Wait for startup, then access http://localhost:8080
3. Test: login flow, dashboard loads, configuration pages work
4. Check browser console for errors

**Production build verified 2026-02-15** (stale — significant changes since; re-verify before release): Docker build, registration, login, dashboard, configuration pages all working. Production standalone mode is clean.

**Known dev-mode issues** (do not affect production):
- The dev compose (`docker-compose.yml`) runs Next.js in Turbopack dev mode. After a `down -v` (which deletes the `node_modules` volume), the `start-nextjs.sh` script auto-installs dependencies but Turbopack may produce stale module chunks for `lib/utils.ts` (e.g. `formatCurrency is not a function`). Workaround: restart the container once after the initial `npm install` completes, or test with a production container (`docker run` without source mounts).
- Hydration mismatch warning (debug level) from Sonner Toaster's deferred client-side mount — cosmetic only, does not crash production builds.

**Resolved issues:**
- ~~500 errors~~ — Fixed: `RateLimitSensitive` middleware crashed when session wasn't initialized on 2FA verify; `UserService::deleteUser()` failed if `user_onboardings` table didn't exist; `MailSettingController::reset()` didn't translate frontend keys to schema keys; webhook encryption migration lacked `APP_KEY` guard.
