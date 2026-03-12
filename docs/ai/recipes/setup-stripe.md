# Set Up Stripe Payments

Step-by-step guide to enable and configure Stripe for payment processing in a Sourdough deployment.

## When to Use

- You want to accept payments via Stripe.
- You're setting up Stripe for the first time on a deployment.

## Critical Principles

1. **Enable the feature first** — Stripe is disabled by default. Enable it via the admin UI or `stripe.enabled` setting.
2. **Use test mode initially** — Always verify with test keys before switching to live.
3. **All keys are stored encrypted** — Secret keys use SettingService with `encrypted: true` in the schema.

## Files

| File | Purpose |
|------|---------|
| `backend/config/stripe.php` | Config with env defaults |
| `backend/config/settings-schema.php` | `stripe` group definition |
| `backend/app/Providers/ConfigServiceProvider.php` | `injectStripeConfig()` boot-time injection |
| `.env.example` | Environment variable reference |
| `frontend/app/(dashboard)/configuration/stripe/page.tsx` | Admin configuration UI |

## Steps

### 1. Stripe Account Setup (one-time)

In the [Stripe Dashboard](https://dashboard.stripe.com/):

1. Create or upgrade your Stripe account and complete identity verification.
2. Note your credentials:
   - **Secret Key** (starts with `sk_test_` or `sk_live_`)
   - **Publishable Key** (starts with `pk_test_` or `pk_live_`)
3. Set up a webhook endpoint:
   - URL: `{APP_URL}/stripe/webhook`
   - Events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`
   - Note the **Webhook Signing Secret** (starts with `whsec_`)

### 2. Configure Environment Variables (Option A)

Add to your `.env` file:

```bash
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_MODE=test
STRIPE_CURRENCY=usd
```

### 3. Configure via Admin UI (Option B)

1. Go to **Configuration → Stripe**.
2. Toggle **Enable Stripe** on.
3. Select mode (Test / Live) and enter API keys (Secret Key, Publishable Key, Webhook Secret).
4. Click **Save**.

### 4. Test Connection

Click the **Test Connection** button on the Stripe configuration page. This calls `StripeService::testConnection()` which retrieves your account info from the Stripe API.

### 5. Switch to Live Mode

When ready for production:

1. Replace test keys with live keys in the Stripe configuration.
2. Switch mode from "Test" to "Live".
3. Update the webhook endpoint in Stripe to use live mode.
4. Test a real payment.

## Checklist

- [ ] Stripe account created with identity verification
- [ ] `stripe.enabled` setting turned on (admin UI or settings)
- [ ] API keys set (Secret, Publishable, Webhook Secret)
- [ ] Test Connection succeeds
- [ ] Webhook endpoint configured in Stripe dashboard
- [ ] Test payment processed successfully

## Common Mistakes

- **❌ Forgetting the webhook secret** — Webhooks will fail signature verification without it.
- **✅ Always set `STRIPE_WEBHOOK_SECRET`** — Copy it from Stripe Dashboard → Webhooks → Signing secret.

- **❌ Using live keys in test mode** — Payments will process real charges.
- **✅ Match keys to the mode** — `sk_test_*` for test mode, `sk_live_*` for live mode.

- **❌ Forgetting to enable Stripe** — The feature is disabled by default. `StripeService::isEnabled()` checks both `stripe.enabled` AND that a secret key is configured.
- **✅ Enable via admin UI or set `stripe.enabled` to `true`** — Navigation items only appear when the feature flag is active.

## Related

- [ADR-026: Stripe Integration](../../adr/026-stripe-integration.md)
- [Pattern: Stripe Service](../patterns/stripe-service.md)
- [Recipe: Add Payment Flow](add-payment-flow.md)
