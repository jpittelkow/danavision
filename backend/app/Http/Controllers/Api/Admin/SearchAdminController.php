<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\AuditService;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchAdminController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private SearchService $searchService,
        private AuditService $auditService
    ) {}

    /**
     * Get index statistics (document counts per index).
     */
    public function stats(): JsonResponse
    {
        $stats = $this->searchService->getIndexStats();

        return $this->dataResponse(['stats' => $stats]);
    }

    /**
     * Get current Meilisearch connection health status.
     */
    public function health(): JsonResponse
    {
        $health = $this->searchService->getHealth();

        return $this->dataResponse($health);
    }

    /**
     * Test Meilisearch connection with provided credentials.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $request->validate([
            'host' => ['required', 'string', 'url'],
            'api_key' => ['nullable', 'string'],
        ]);

        $result = $this->searchService->testConnection(
            $request->input('host'),
            $request->input('api_key')
        );

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 422);
        }

        return $this->successResponse($result['message']);
    }

    /**
     * Reindex search (single model or all).
     */
    public function reindex(Request $request): JsonResponse
    {
        $request->validate([
            'model' => ['nullable', 'string', 'in:pages,users,user_groups,notifications,email_templates,notification_templates,api_tokens,ai_providers,webhooks'],
        ]);

        $model = $request->input('model');
        $result = $this->searchService->reindexAndReport($model);

        if (!$result['success']) {
            return $this->errorResponse($result['message'], 422);
        }

        $user = $request->user();
        $auditAction = $model !== null ? 'search.reindex' : 'search.reindex_all';
        $auditMeta = $model !== null ? ['model' => $model] : ['models' => $result['models'] ?? []];
        $this->auditService->logUserAction($auditAction, $user->id, null, 'info', $auditMeta);

        $responseData = collect($result)->only(['model', 'models', 'count', 'output'])->filter()->all();

        return $this->successResponse($result['message'], $responseData);
    }
}
