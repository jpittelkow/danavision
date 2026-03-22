# Roadmaps & Plans

Development roadmaps for DanaVision v2.

## V1 Reference

DanaVision v1 source code: `C:\Users\jpitt_6932a9v\code\danavision`. Reference this when building v2 features to ensure feature parity.

## Bug Tracker

Persistent log of suspected bugs and issues to investigate. Always kept up to date.

**[Bug Tracker](plans/bug-tracker.md)**

## Active Development

Currently in progress. Complete these before starting new work.

### Ask Dana — AI Shopping Assistant

**Status:** In Progress
**Priority:** High

Conversational AI assistant ("Ask Dana") that makes the app's data and capabilities accessible via natural language. Uses tool-use pattern with existing services. See [journal entry](journal/2026-03-21-ask-dana-ai-assistant.md).

### Dashboard Widget Cleanup

**Status:** Ready to Start
**Priority:** Medium

Remove all dashboard widgets except:
- Welcome widget
- Shopping widget
- Shopping Activity widget

Add quick-action buttons for:
- Smart Add
- Current Top Deals

## Next Up

Ready to start. These are unblocked and can begin immediately.

### Enhanced List Sharing

**Status:** Ready to Start
**Priority:** Low

Expand the existing list sharing infrastructure (ListShare model, ListSharingService, ShareDialog) into a full collaborative sharing experience. Current state: basic invite-by-email with view/edit permissions, accept/decline flow, and notifications.

Enhancements:
- **Shareable links** — Generate public or password-protected URLs so users can share lists without requiring the recipient to have an account
- **Real-time collaboration** — Use Reverb broadcasting so shared list edits appear live for all participants
- **"Shared with me" view** — Dedicated page showing all lists shared with the current user, with accept/decline/leave actions
- **Group sharing** — Share a list with an entire user group in one action
- **Activity feed** — Show who added, checked off, or removed items on a shared list
- **Per-item assignment** — Assign specific items to specific collaborators (e.g., "you grab the milk")

## Planned Features

Requires foundation work or longer-term planning.

### Web Crawling — Firecrawl Fallback (Optional)

**Status:** Planned
**Priority:** Low
**Depends on:** Shopping List Analysis (Phases 1–6 complete)

CrawlAI is the primary crawler (self-hosted, free). Firecrawl can be added as a paid SaaS fallback for stores with aggressive anti-bot protection. Key stubs exist:
- `backend/app/Services/Crawler/FirecrawlService.php`
- `backend/app/Services/Crawler/StoreDiscoveryService.php`

### RainforestAPI Integration

**Status:** Planned
**Priority:** Low

Amazon-specific price API for reliable Amazon product pricing. Complements SerpAPI which covers Google Shopping results.

### Coupon & Ad Scanner (Photo-to-Pricing)

**Status:** Planned
**Priority:** Medium
**Depends on:** Shopping List Analysis (Phases 1–6 complete), LLM Integration

Photograph coupons, weekly mailers, and store ads to automatically extract deals and apply them to pricing logic. Key capabilities:

- **Photo capture** — Camera or gallery upload of physical coupons, weekly circulars, and store flyers
- **LLM-powered extraction** — Vision model (GPT-4o, Claude, Gemini) parses the image to extract: product name, discount amount/percentage, sale price, valid date range, store, and any conditions (min purchase, limit per customer, etc.)
- **Date-aware pricing** — Extracted deals include start/end dates so the pricing engine only applies them during their active window
- **Coupon stacking** — Combine scanned coupons with existing crawled store prices to show true effective price
- **Deal library** — Saved deals viewable per store with expiration status (active, upcoming, expired)
- **Shopping list integration** — Automatically match scanned deals to items on active shopping lists and surface savings

### Daily Summary Email

**Status:** Planned
**Priority:** Medium
**Depends on:** Notification Integration (Phase 4), Price Tracking (Phase 2)

Scheduled email summarizing price drops, savings, all-time lows, and items below target price. Configurable send time per user.

## Completed (DanaVision v2)

- [x] **Shopping List Analysis — All 6 Phases** — Completed 2026-03-21. Full price search (SerpAPI, Kroger, CrawlAI, Firecrawl), store management, sharing, notifications, dashboard widgets, and scheduled crawling. See [plan](plans/generic-crafting-squid.md) and [journal](journal/2026-03-21-phase-6-scheduled-crawling.md).

## Completed (Sourdough-era — Closed)

All items below were inherited from the Sourdough template and are not applicable to DanaVision v2. Archived for reference.

- [x] **OWASP ASVS Level 2 Audit** — Closed (Sourdough item)
- [x] **Re-evaluate Stripe Integration** — Closed (Sourdough item)
- [x] **Documentation Review** — Closed (Sourdough item)
- [x] **Get Cooking Script Audit** — Closed (Sourdough item)
- [x] **AI-Readable Changelog Export** — Closed (Sourdough item)
- [x] **User Build Verification** — Closed (Sourdough item)

See **[roadmap-archive.md](roadmap-archive.md)** for Sourdough-era completed roadmaps and journal entries.

## Integration Costs

Reference for paid third-party integrations used by DanaVision. All integrations are optional — the app runs fully self-hosted with no paid services required. Costs only apply when an admin configures and enables a paid provider.

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
