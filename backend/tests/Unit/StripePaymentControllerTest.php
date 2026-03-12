<?php

use App\Http\Controllers\Api\StripePaymentController;
use App\Models\Payment;
use App\Services\Stripe\StripeService;

describe('StripePaymentController', function () {

    beforeEach(function () {
        $this->stripeService = $this->createMock(StripeService::class);
        $this->controller = new StripePaymentController(
            $this->stripeService,
        );
    });

    describe('index', function () {
        it('returns paginated user payments', function () {
            $user = createUser();
            $request = Illuminate\Http\Request::create('/api/payments', 'GET');
            $request->setUserResolver(fn () => $user);

            $response = $this->controller->index($request);
            $data = $response->getData(true);

            expect($response->getStatusCode())->toBe(200);
            expect($data)->toHaveKey('data');
        });
    });

    describe('show', function () {
        it('returns payment when user owns it', function () {
            $user = createUser();
            $payment = Payment::factory()->create(['user_id' => $user->id]);

            $request = Illuminate\Http\Request::create('/api/payments/' . $payment->id, 'GET');
            $request->setUserResolver(fn () => $user);

            $response = $this->controller->show($request, $payment);
            $data = $response->getData(true);

            expect($response->getStatusCode())->toBe(200);
            expect($data['payment']['id'])->toBe($payment->id);
        });

        it('returns 403 when user does not own payment and lacks permission', function () {
            $owner = createUser();
            $other = createUser();
            $payment = Payment::factory()->create(['user_id' => $owner->id]);

            $request = Illuminate\Http\Request::create('/api/payments/' . $payment->id, 'GET');
            $request->setUserResolver(fn () => $other);

            $response = $this->controller->show($request, $payment);

            expect($response->getStatusCode())->toBe(403);
        });
    });

    describe('createIntent', function () {
        it('creates payment intent and local payment record', function () {
            config(['stripe.currency' => 'usd']);

            $user = createUser();

            $this->stripeService
                ->method('initiatePayment')
                ->willReturn([
                    'success' => true,
                    'payment_id' => 42,
                    'client_secret' => 'pi_test_123_secret',
                ]);

            $request = Illuminate\Http\Request::create('/api/payments/intent', 'POST', [
                'amount' => 1000,
                'description' => 'Test payment',
            ]);
            $request->setUserResolver(fn () => $user);

            $response = $this->controller->createIntent($request);
            $data = $response->getData(true);

            expect($response->getStatusCode())->toBe(201);
            expect($data['client_secret'])->toBe('pi_test_123_secret');
            expect($data['payment_id'])->toBe(42);
        });

        it('returns 500 when initiatePayment fails', function () {
            $this->stripeService
                ->method('initiatePayment')
                ->willReturn(['success' => false, 'error' => 'Stripe API error']);

            $request = Illuminate\Http\Request::create('/api/payments/intent', 'POST', [
                'amount' => 1000,
            ]);
            $request->setUserResolver(fn () => createUser());

            $response = $this->controller->createIntent($request);

            expect($response->getStatusCode())->toBe(500);
        });
    });

    describe('adminIndex', function () {
        it('returns paginated payments with user relation', function () {
            $user = createUser();
            Payment::factory()->count(3)->create(['user_id' => $user->id]);

            $request = Illuminate\Http\Request::create('/api/payments/admin', 'GET');
            $request->setUserResolver(fn () => createAdminUser());

            $response = $this->controller->adminIndex($request);
            $data = $response->getData(true);

            expect($response->getStatusCode())->toBe(200);
            expect($data)->toHaveKey('data');
            expect(count($data['data']))->toBe(3);
        });
    });
});
