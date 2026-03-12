<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\UrlValidationService;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    use ApiResponseTrait;
    public function __construct(
        private UrlValidationService $urlValidator,
        private WebhookService $webhookService
    ) {}

    /**
     * Get all webhooks.
     */
    public function index(): JsonResponse
    {
        $webhooks = Webhook::orderBy('created_at', 'desc')->get()->map(function ($webhook) {
            $data = $webhook->toArray();
            $data['secret_set'] = !empty($webhook->getRawOriginal('secret'));
            return $data;
        });

        return $this->dataResponse([
            'webhooks' => $webhooks,
        ]);
    }

    /**
     * Get a single webhook.
     */
    public function show(Webhook $webhook): JsonResponse
    {
        $data = $webhook->toArray();
        $data['secret_set'] = !empty($webhook->getRawOriginal('secret'));

        return $this->dataResponse(['webhook' => $data]);
    }

    /**
     * Create a new webhook.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url'],
            'secret' => ['sometimes', 'nullable', 'string'],
            'events' => ['required', 'array'],
            'events.*' => ['string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        // Validate URL for SSRF protection
        if (!$this->urlValidator->validateUrl($validated['url'])) {
            return $this->errorResponse('Invalid webhook URL: URLs pointing to internal or private addresses are not allowed', 422);
        }

        $webhook = Webhook::create($validated);

        // Return secret only on creation (it's hidden from all other responses)
        $response = $webhook->toArray();
        $response['secret'] = $validated['secret'] ?? null;
        $response['secret_set'] = !empty($validated['secret']);

        return $this->createdResponse('Webhook created successfully', [
            'webhook' => $response,
        ]);
    }

    /**
     * Update a webhook.
     */
    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url'],
            'secret' => ['sometimes', 'nullable', 'string'],
            'events' => ['sometimes', 'array'],
            'events.*' => ['string'],
            'active' => ['sometimes', 'boolean'],
        ]);

        // Validate URL for SSRF protection if being updated
        if (isset($validated['url']) && !$this->urlValidator->validateUrl($validated['url'])) {
            return $this->errorResponse('Invalid webhook URL: URLs pointing to internal or private addresses are not allowed', 422);
        }

        $webhook->update($validated);

        $fresh = $webhook->fresh();
        $data = $fresh->toArray();
        $data['secret_set'] = !empty($fresh->getRawOriginal('secret'));

        return $this->successResponse('Webhook updated successfully', [
            'webhook' => $data,
        ]);
    }

    /**
     * Delete a webhook.
     */
    public function destroy(Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return $this->successResponse('Webhook deleted successfully');
    }

    /**
     * Get webhook deliveries.
     */
    public function deliveries(Webhook $webhook, Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', config('app.pagination.default'));

        $deliveries = $webhook->deliveries()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->dataResponse($deliveries);
    }

    /**
     * Test a webhook.
     */
    public function test(Webhook $webhook): JsonResponse
    {
        $result = $this->webhookService->sendTest($webhook);

        $status = ($result['ssrf_blocked'] ?? false) ? 422 : ($result['success'] ? 200 : 500);

        return response()->json([
            'message' => $result['message'],
            'success' => $result['success'],
            'status_code' => $result['status_code'] ?? null,
        ], $status);
    }
}
