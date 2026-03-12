<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\NovuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User-facing Novu endpoints (e.g. HMAC for notification center).
 */
class NovuController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private NovuService $novuService
    ) {}

    /**
     * Get HMAC hash for the current user's subscriber ID (for Novu Inbox auth).
     */
    public function subscriberHash(Request $request): JsonResponse
    {
        if (! $this->novuService->isEnabled()) {
            return $this->errorResponse('Novu is not enabled', 404);
        }

        $user = $request->user();
        if (! $user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $subscriberId = $this->novuService->subscriberId($user);
        $apiKey = config('novu.api_key');
        if (empty($apiKey)) {
            return $this->errorResponse('Novu API key not configured', 500);
        }

        $hash = hash_hmac('sha256', $subscriberId, $apiKey);

        return $this->dataResponse(['subscriber_hash' => $hash]);
    }
}
