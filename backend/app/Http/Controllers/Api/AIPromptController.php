<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\AIPrompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIPromptController extends Controller
{
    use ApiResponseTrait;

    /**
     * List user's custom AI prompts.
     */
    public function index(Request $request): JsonResponse
    {
        $prompts = AIPrompt::where('user_id', $request->user()->id)
            ->orderBy('prompt_type')
            ->get();

        return response()->json(['data' => $prompts]);
    }

    /**
     * Create a custom AI prompt.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt_type' => ['required', 'string', 'in:product_identification,price_recommendation,product_aggregation'],
            'prompt_text' => ['required', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Deactivate existing prompts of same type for this user
        if ($validated['is_active'] ?? true) {
            AIPrompt::where('user_id', $request->user()->id)
                ->where('prompt_type', $validated['prompt_type'])
                ->update(['is_active' => false]);
        }

        $prompt = AIPrompt::create([
            'user_id' => $request->user()->id,
            'prompt_type' => $validated['prompt_type'],
            'prompt_text' => $validated['prompt_text'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->createdResponse('Custom prompt created', ['data' => $prompt]);
    }

    /**
     * Update a custom AI prompt.
     */
    public function update(Request $request, AIPrompt $prompt): JsonResponse
    {
        $this->authorizeAccess($request, $prompt);

        $validated = $request->validate([
            'prompt_text' => ['sometimes', 'required', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // If activating, deactivate other prompts of same type
        if (($validated['is_active'] ?? false) && !$prompt->is_active) {
            AIPrompt::where('user_id', $request->user()->id)
                ->where('prompt_type', $prompt->prompt_type)
                ->where('id', '!=', $prompt->id)
                ->update(['is_active' => false]);
        }

        $prompt->update($validated);

        return $this->successResponse('Prompt updated', ['data' => $prompt]);
    }

    /**
     * Delete a custom AI prompt.
     */
    public function destroy(Request $request, AIPrompt $prompt): JsonResponse
    {
        $this->authorizeAccess($request, $prompt);

        $prompt->delete();

        return $this->deleteResponse('Prompt deleted');
    }

    /**
     * Get the active prompt for a given type (user's custom or system default).
     */
    public function active(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt_type' => ['required', 'string', 'in:product_identification,price_recommendation,product_aggregation'],
        ]);

        $prompt = AIPrompt::where('user_id', $request->user()->id)
            ->where('prompt_type', $validated['prompt_type'])
            ->where('is_active', true)
            ->first();

        return response()->json(['data' => $prompt]);
    }

    private function authorizeAccess(Request $request, AIPrompt $prompt): void
    {
        if ($prompt->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this prompt.');
        }
    }
}
