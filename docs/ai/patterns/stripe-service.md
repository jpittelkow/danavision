# Stripe Service Pattern

Use `StripeService` for all payment operations. Do not use the Stripe PHP SDK directly — the service handles client initialization and feature gating.

## Usage

```php
use App\Services\Stripe\StripeService;

// Check if Stripe is enabled (enabled setting + secret key present)
if ($stripeService->isEnabled()) { ... }

// Test connection (retrieves account info from Stripe API)
$result = $stripeService->testConnection();
// Returns: ['success' => true, 'account_id' => 'acct_...']

// Create or retrieve a Stripe customer for a user
$result = $stripeService->createCustomer($user);
// Returns: ['success' => true, 'customer_id' => 'cus_...']
// Idempotent: returns existing customer if already created

// Create payment intent
$result = $stripeService->createPaymentIntent([
    'amount' => 2000,                         // cents (required)
    'currency' => 'usd',                      // optional, defaults to config
    'customer_id' => 'cus_...',               // optional
    'description' => 'Order #123',            // optional
    'metadata' => ['order_id' => 123],        // optional
]);
// Returns: ['success' => true, 'payment_intent_id' => 'pi_...', 'client_secret' => '...']

// Refund (full or partial)
$result = $stripeService->refund('pi_...', 500);  // partial: 500 cents
$result = $stripeService->refund('pi_...');        // full refund (omit amount)
// Returns: ['success' => true, 'refund_id' => 're_...']
```

## Feature Gating

`isEnabled()` checks both the `stripe.enabled` setting AND that a `secret_key` is configured. The feature is disabled by default. When disabled, Stripe and Payment History nav items are hidden from the sidebar (via `featureFlag: "stripe"` in the configuration layout).

## Return Value Pattern

All service methods return arrays with a `success` boolean. On failure, an `error` string is included:

```php
$result = $stripeService->createPaymentIntent([...]);
if (!$result['success']) {
    // $result['error'] contains the error message
    return response()->json(['error' => $result['error']], 500);
}
// $result['payment_intent_id'], $result['client_secret']
```

**Key files:** `backend/app/Services/Stripe/StripeService.php`, `backend/config/stripe.php`, `backend/config/settings-schema.php`.

**Related:** [Recipe: Setup Stripe](../recipes/setup-stripe.md), [Recipe: Add Payment Flow](../recipes/add-payment-flow.md), [ADR-026](../../adr/026-stripe-integration.md).
