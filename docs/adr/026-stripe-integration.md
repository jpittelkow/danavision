# ADR-026: Stripe Integration

## Status

Accepted (revised 2026-03-11 — removed Stripe Connect, simplified to plain Stripe)

## Date

2026-02-21 (original), 2026-03-11 (revised)

## Context

Sourdough needs an optional payment processing module. The original implementation used Stripe Connect with destination charges and a 1% platform fee, but this was removed because:

1. The dual license (MIT + Commercial) was the only non-MIT code and created friction for adopters
2. Most forks of a template project won't process payments, making the Connect platform overhead unjustified
3. The 1% fee model generated negligible revenue relative to compliance/maintenance burden

## Decision

We provide a **plain Stripe integration** as an optional, feature-gated module. Fully MIT licensed.

### Feature-Gated

- Stripe is disabled by default. Admin enables it via `stripe.enabled` setting.
- Navigation items (Stripe settings, Payment History) only appear when the feature flag is true.
- `StripeService::isEnabled()` checks both the `stripe.enabled` setting and that a secret key is configured.
- Follows the same pattern as Novu and GraphQL feature gating.

### Configuration

- Settings stored via SettingService (`stripe` group in `settings-schema.php`).
- Environment variable fallback for keys (`STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, etc.).
- `ConfigServiceProvider::injectStripeConfig()` loads settings at boot into `config('stripe.*')`.
- Secret keys are encrypted in the database per schema definition.

### Webhook Handling

- Public endpoint at `POST /stripe/webhook` (no auth middleware).
- Signature verification via `stripe-php` SDK.
- Idempotent: `stripe_webhook_events` table deduplicates by `stripe_event_id`.
- Handled events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`.

### Usage Tracking

- Payment events tracked via `UsageTrackingService::recordPayment()`.
- `budget_payments` setting enables budget alerting for payment costs.

## Consequences

### Positive

- Fully MIT — no licensing friction for adopters.
- Feature-gated — clean sidebar when payments aren't needed.
- Simple setup — just API keys, no OAuth/Connect onboarding flow.
- Usage tracking integration gives cost visibility alongside other integrations.

### Negative

- No built-in checkout flow — forks must build their own payment UI on top of the infrastructure.
- No platform monetization via Stripe — removed with Connect.

## Related Decisions

- [ADR-014: Database Settings with Env Fallback](014-database-settings-env-fallback.md) — settings storage pattern
- [ADR-012: Admin-Only Settings](012-admin-only-settings.md) — admin gating for configuration

## Key Files

- `backend/app/Services/Stripe/StripeService.php` — payment intents, customers, refunds
- `backend/app/Services/Stripe/StripeWebhookService.php` — event handling, idempotency, usage tracking
- `backend/config/stripe.php` — config with env defaults
- `backend/config/settings-schema.php` — `stripe` group
- `backend/app/Http/Controllers/Api/StripeSettingController.php` — settings CRUD + test connection
- `backend/app/Http/Controllers/Api/StripePaymentController.php` — payment listing + create intent
- `backend/app/Http/Controllers/Api/StripeWebhookController.php` — public webhook endpoint
- `backend/app/Models/Payment.php`, `StripeCustomer.php`, `StripeWebhookEvent.php`
- `frontend/lib/stripe.ts` — Stripe.js loader, hooks
- `frontend/app/(dashboard)/configuration/stripe/page.tsx` — settings UI
- `frontend/app/(dashboard)/configuration/payments/page.tsx` — payment history

## Recipes

- [Setup Stripe](../ai/recipes/setup-stripe.md)
- [Add Payment Flow](../ai/recipes/add-payment-flow.md)
- [Handle Stripe Webhooks](../ai/recipes/handle-stripe-webhooks.md)
