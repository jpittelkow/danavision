<?php

namespace App\Services\Stripe;

use App\Models\Payment;
use App\Models\StripeCustomer;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripeService
{
    private ?StripeClient $client = null;

    public function isEnabled(): bool
    {
        return filter_var(config('stripe.enabled'), FILTER_VALIDATE_BOOLEAN)
            && ! empty(config('stripe.secret_key'));
    }

    /**
     * Test connection to Stripe API by retrieving account info.
     *
     * @return array{success: bool, error?: string, account_id?: string}
     */
    public function testConnection(): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'error' => 'Stripe is not enabled or secret key is missing'];
        }

        try {
            $client = $this->getClient();
            $account = $client->accounts->retrieve('self');

            return [
                'success' => true,
                'account_id' => $account->id,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a Stripe customer for a user.
     *
     * @return array{success: bool, customer_id?: string, error?: string}
     */
    public function createCustomer(User $user): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'error' => 'Stripe is not enabled'];
        }

        $existing = StripeCustomer::where('user_id', $user->id)->first();
        if ($existing) {
            return [
                'success' => true,
                'customer_id' => $existing->stripe_customer_id,
            ];
        }

        try {
            $client = $this->getClient();
            $customer = $client->customers->create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            StripeCustomer::create([
                'user_id' => $user->id,
                'stripe_customer_id' => $customer->id,
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
            ];
        } catch (\Throwable $e) {
            Log::warning('Stripe customer creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a payment intent.
     *
     * @param  array{amount: int, currency?: string, customer_id?: string, description?: string, metadata?: array}  $params
     * @return array{success: bool, payment_intent_id?: string, client_secret?: string, error?: string}
     */
    public function createPaymentIntent(array $params): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'error' => 'Stripe is not enabled'];
        }

        if (! isset($params['amount']) || $params['amount'] <= 0) {
            return ['success' => false, 'error' => 'Amount must be a positive integer (in cents)'];
        }

        try {
            $client = $this->getClient();
            $currency = $params['currency'] ?? config('stripe.currency', 'usd');

            $intentParams = [
                'amount' => $params['amount'],
                'currency' => $currency,
            ];

            if (! empty($params['customer_id'])) {
                $intentParams['customer'] = $params['customer_id'];
            }
            if (! empty($params['description'])) {
                $intentParams['description'] = $params['description'];
            }
            if (! empty($params['metadata'])) {
                $intentParams['metadata'] = $params['metadata'];
            }

            $intent = $client->paymentIntents->create($intentParams);

            return [
                'success' => true,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
            ];
        } catch (\Throwable $e) {
            Log::warning('Stripe payment intent creation failed', [
                'amount' => $params['amount'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Initiate a full payment flow: create/find Stripe customer, create payment intent,
     * and persist the local Payment record.
     *
     * @return array{success: bool, payment_id?: int, client_secret?: string, error?: string}
     */
    public function initiatePayment(
        User $user,
        int $amount,
        string $currency,
        ?string $description = null,
        array $metadata = []
    ): array {
        $customerResult = $this->createCustomer($user);
        if (!$customerResult['success']) {
            return ['success' => false, 'error' => $customerResult['error'] ?? 'Failed to create Stripe customer'];
        }

        $result = $this->createPaymentIntent([
            'amount' => $amount,
            'currency' => $currency,
            'customer_id' => $customerResult['customer_id'],
            'description' => $description,
            'metadata' => $metadata,
        ]);

        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to create payment intent'];
        }

        $stripeCustomer = StripeCustomer::where('stripe_customer_id', $customerResult['customer_id'])->first();

        $payment = Payment::create([
            'user_id' => $user->id,
            'stripe_customer_id' => $stripeCustomer?->id,
            'stripe_payment_intent_id' => $result['payment_intent_id'],
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'requires_payment_method',
            'description' => $description,
            'metadata' => !empty($metadata) ? $metadata : null,
        ]);

        return [
            'success' => true,
            'payment_id' => $payment->id,
            'client_secret' => $result['client_secret'],
        ];
    }

    /**
     * Refund a payment intent (full or partial).
     *
     * @return array{success: bool, refund_id?: string, error?: string}
     */
    public function refund(string $paymentIntentId, ?int $amount = null): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'error' => 'Stripe is not enabled'];
        }

        try {
            $client = $this->getClient();
            $refundParams = ['payment_intent' => $paymentIntentId];

            if ($amount !== null) {
                $refundParams['amount'] = $amount;
            }

            $refund = $client->refunds->create($refundParams);

            return [
                'success' => true,
                'refund_id' => $refund->id,
            ];
        } catch (\Throwable $e) {
            Log::warning('Stripe refund failed', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lazy-initialize the Stripe client.
     */
    public function getClient(): StripeClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $this->client = new StripeClient(config('stripe.secret_key'));

        return $this->client;
    }
}
