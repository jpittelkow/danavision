<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ApiToken;
use App\Services\ApiKeyService;
use App\Services\AuditService;
use App\Services\SettingService;
use App\Services\UsageStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphQLSettingController extends Controller
{
    use ApiResponseTrait;

    private const GROUP = 'graphql';

    public function __construct(
        private SettingService $settingService,
        private AuditService $auditService,
        private ApiKeyService $apiKeyService,
        private UsageStatsService $usageStatsService
    ) {}

    /**
     * Get GraphQL settings.
     */
    public function show(): JsonResponse
    {
        $settings = $this->settingService->getGroup(self::GROUP);

        return $this->dataResponse(['settings' => $settings]);
    }

    /**
     * Update GraphQL settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'max_keys_per_user' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'default_rate_limit' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'introspection_enabled' => ['sometimes', 'boolean'],
            'max_query_depth' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'max_query_complexity' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'max_result_size' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'key_rotation_grace_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'cors_allowed_origins' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $userId = $request->user()->id;
        $oldSettings = $this->settingService->getGroup(self::GROUP);

        foreach ($validated as $key => $value) {
            $this->settingService->set(self::GROUP, $key, $value === '' ? null : $value, $userId);
        }

        $this->auditService->logSettings(self::GROUP, $oldSettings, $validated, $userId);

        return $this->successResponse('GraphQL settings updated successfully');
    }

    /**
     * List all API keys across all users (admin).
     */
    public function adminApiKeys(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'user_id', 'user', 'expiring_soon']);
        $keys = $this->apiKeyService->listAdminKeys($filters, (int) $request->input('per_page', 50));

        $keys->getCollection()->transform(function (ApiToken $token) {
            return [
                'id' => $token->id,
                'user' => $token->user ? [
                    'id' => $token->user->id,
                    'name' => $token->user->name,
                    'email' => $token->user->email,
                ] : null,
                'name' => $token->name,
                'key_prefix' => $token->key_prefix,
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'revoked_at' => $token->revoked_at?->toIso8601String(),
                'status' => $this->apiKeyService->getKeyStatus($token),
            ];
        });

        return response()->json($keys);
    }

    /**
     * Get API key summary statistics (admin).
     */
    public function adminApiKeyStats(): JsonResponse
    {
        $baseQuery = ApiToken::withTrashed()
            ->whereNotNull('key_prefix')
            ->where('key_prefix', 'like', 'sk_%');

        $total = (clone $baseQuery)->count();

        $active = (clone $baseQuery)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNull('deleted_at')
            ->count();

        $expiringSoon = (clone $baseQuery)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(7))
            ->whereNull('revoked_at')
            ->whereNull('deleted_at')
            ->count();

        $neverUsed = (clone $baseQuery)
            ->whereNull('last_used_at')
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNull('deleted_at')
            ->count();

        return $this->dataResponse([
            'total' => $total,
            'active' => $active,
            'expiring_soon' => $expiringSoon,
            'never_used' => $neverUsed,
        ]);
    }

    /**
     * Revoke any user's API key (admin).
     */
    public function adminRevokeKey(Request $request, int $id): JsonResponse
    {
        // Use withTrashed so we can distinguish "not found" from "already revoked"
        $token = ApiToken::withTrashed()
            ->whereNotNull('key_prefix')
            ->where('key_prefix', 'like', 'sk_%')
            ->find($id);

        if (! $token) {
            return $this->errorResponse('API key not found', 404);
        }

        if ($token->isRevoked()) {
            return $this->errorResponse('API key is already revoked', 422);
        }

        $this->apiKeyService->revoke($token);

        return $this->successResponse('API key revoked successfully');
    }

    /**
     * Get API usage statistics.
     */
    public function usageStats(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 30);

        return $this->dataResponse($this->usageStatsService->getApiUsageStats($days));
    }
}
