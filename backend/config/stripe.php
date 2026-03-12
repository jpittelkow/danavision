<?php

/**
 * Stripe Configuration
 *
 * Values are overridden at boot by ConfigServiceProvider when stored in database.
 * See config/settings-schema.php for the full list of migratable settings.
 */

return [
    'enabled' => (bool) env('STRIPE_ENABLED', false),
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => env('STRIPE_CURRENCY', 'usd'),
    'mode' => env('STRIPE_MODE', 'test'),
];
