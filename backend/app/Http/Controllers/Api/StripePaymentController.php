<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Payment;
use App\Services\Stripe\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripePaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private StripeService $stripeService
    ) {}

    /**
     * List the authenticated user's payments.
     */
    public function index(Request $request): JsonResponse
    {

        $payments = Payment::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(min($request->integer('per_page', config('app.pagination.default', 20)), 100));

        return $this->dataResponse($payments);
    }

    /**
     * Show a single payment (user must own it or have payments.manage).
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {

        if ($payment->user_id !== $request->user()->id && ! $request->user()->can('payments.manage')) {
            return $this->errorResponse('Forbidden', 403);
        }

        return $this->dataResponse(['payment' => $payment]);
    }

    /**
     * Create a Stripe payment intent.
     */
    public function createIntent(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:50'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $currency = $validated['currency'] ?? config('stripe.currency', 'usd');

        $result = $this->stripeService->initiatePayment(
            user: $request->user(),
            amount: $validated['amount'],
            currency: $currency,
            description: $validated['description'] ?? null,
            metadata: $validated['metadata'] ?? [],
        );

        if (!$result['success']) {
            return $this->errorResponse($result['error'] ?? 'Payment failed', 500);
        }

        return $this->dataResponse([
            'payment_id' => $result['payment_id'],
            'client_secret' => $result['client_secret'],
        ], 201);
    }

    /**
     * List all payments across all users (admin).
     */
    public function adminIndex(Request $request): JsonResponse
    {

        $payments = Payment::with('user')
            ->orderByDesc('created_at')
            ->paginate(min($request->integer('per_page', config('app.pagination.audit_log', 50)), 100));

        return $this->dataResponse($payments);
    }
}
