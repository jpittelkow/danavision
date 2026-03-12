<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LLMSettingController extends Controller
{
    use ApiResponseTrait;

    private const GROUP = 'llm';

    public function __construct(
        private SettingService $settingService
    ) {}

    /**
     * Get system-wide LLM settings.
     */
    public function show(): JsonResponse
    {
        $settings = $this->settingService->getGroup(self::GROUP);

        return $this->dataResponse([
            'settings' => $settings,
        ]);
    }

    /**
     * Update system-wide LLM settings.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['sometimes', 'string', 'in:single,aggregation,council'],
            'primary' => ['sometimes', 'string', 'in:claude,openai,gemini,ollama,bedrock,azure'],
            'timeout' => ['sometimes', 'integer', 'min:10', 'max:600'],
            'logging_enabled' => ['sometimes', 'boolean'],
            'council_min_providers' => ['sometimes', 'integer', 'min:2', 'max:6'],
            'council_strategy' => ['sometimes', 'string', 'in:majority,weighted,synthesize'],
            'aggregation_parallel' => ['sometimes', 'boolean'],
            'aggregation_include_sources' => ['sometimes', 'boolean'],
        ]);

        $userId = $request->user()->id;
        foreach ($validated as $key => $value) {
            $this->settingService->set(self::GROUP, $key, $value, $userId);
        }

        return $this->successResponse('LLM settings updated successfully');
    }

    /**
     * Reset a LLM setting to env default.
     */
    public function reset(Request $request, string $key): JsonResponse
    {
        $schema = config('settings-schema.llm', []);
        if (!array_key_exists($key, $schema)) {
            return $this->errorResponse('Unknown setting key', 422);
        }
        $this->settingService->reset(self::GROUP, $key);

        return $this->successResponse('Setting reset to default');
    }

}
