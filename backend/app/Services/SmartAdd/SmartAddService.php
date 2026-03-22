<?php

namespace App\Services\SmartAdd;

use App\Models\AIJob;
use App\Models\ListItem;
use App\Models\SmartAddQueueItem;
use App\Models\User;
use App\Notifications\SmartAddCompleteNotification;
use App\Services\LLM\LLMOrchestrator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class SmartAddService
{
    public function __construct(
        private readonly LLMOrchestrator $llmOrchestrator,
    ) {}

    /**
     * Identify a product from an image using AI vision.
     *
     * Creates an AIJob and dispatches background identification using
     * LLMOrchestrator's visionQuery with a product identification prompt.
     *
     * @param string $imagePath Path to the uploaded image file
     * @param User $user The user requesting identification
     * @return AIJob The created AI job for tracking
     */
    public function identifyFromImage(string $imagePath, User $user): AIJob
    {
        $aiJob = AIJob::create([
            'user_id' => $user->id,
            'type' => 'smart_add_image',
            'status' => 'pending',
            'input_data' => ['image_path' => $imagePath],
        ]);

        try {
            $imageData = base64_encode(file_get_contents($imagePath));
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

            $prompt = $this->buildVisionIdentificationPrompt();
            $systemPrompt = $this->buildIdentificationSystemPrompt();

            $result = $this->llmOrchestrator->visionQuery(
                user: $user,
                prompt: $prompt,
                imageData: $imageData,
                mimeType: $mimeType,
                systemPrompt: $systemPrompt,
                mode: 'single',
            );

            if ($result['success']) {
                $suggestions = $this->parseIdentificationResponse($result['response']);

                $aiJob->update([
                    'status' => 'completed',
                    'output_data' => $suggestions,
                ]);

                $this->createQueueItems($user, $suggestions, $aiJob, $imagePath);
                $this->notifyComplete($user, count($suggestions), 'image', $aiJob->id);
            } else {
                $aiJob->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Vision query failed',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SmartAddService: image identification failed', [
                'ai_job_id' => $aiJob->id,
                'error' => $e->getMessage(),
            ]);

            $aiJob->markFailed($e->getMessage());
        }

        return $aiJob->fresh();
    }

    /**
     * Identify a product from a text query using AI.
     *
     * Creates an AIJob for text-based product identification using
     * LLMOrchestrator's query method.
     *
     * @param string $query The text description or product name
     * @param User $user The user requesting identification
     * @return AIJob The created AI job for tracking
     */
    public function identifyFromText(string $query, User $user): AIJob
    {
        $aiJob = AIJob::create([
            'user_id' => $user->id,
            'type' => 'smart_add_text',
            'status' => 'pending',
            'input_data' => ['query' => $query],
        ]);

        try {
            $prompt = $this->buildTextIdentificationPrompt($query);
            $systemPrompt = $this->buildIdentificationSystemPrompt();

            $result = $this->llmOrchestrator->query(
                user: $user,
                prompt: $prompt,
                systemPrompt: $systemPrompt,
                mode: 'single',
            );

            if ($result['success']) {
                $suggestions = $this->parseIdentificationResponse($result['response']);

                $aiJob->update([
                    'status' => 'completed',
                    'output_data' => $suggestions,
                ]);

                $this->createQueueItems($user, $suggestions, $aiJob);
                $this->notifyComplete($user, count($suggestions), 'text', $aiJob->id);
            } else {
                $aiJob->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Query failed',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SmartAddService: text identification failed', [
                'ai_job_id' => $aiJob->id,
                'error' => $e->getMessage(),
            ]);

            $aiJob->markFailed($e->getMessage());
        }

        return $aiJob->fresh();
    }

    /**
     * Get pending SmartAdd queue items for a user.
     *
     * @param User $user The user whose queue to retrieve
     * @return Collection Collection of SmartAddQueueItem models
     */
    public function getQueue(User $user): Collection
    {
        return SmartAddQueueItem::where('user_id', $user->id)
            ->where('status', 'ready')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Accept a suggestion and add the selected product to a shopping list.
     *
     * @param SmartAddQueueItem $item The queue item containing suggestions
     * @param int $selectedIndex The index of the selected suggestion (0-based)
     * @param int $shoppingListId The shopping list to add the item to
     * @return ListItem The newly created list item
     * @throws \InvalidArgumentException If the selected index is out of range
     */
    public function acceptItem(SmartAddQueueItem $item, int $selectedIndex, int $shoppingListId): ListItem
    {
        $suggestions = $item->product_data ?? [];

        if (!isset($suggestions[$selectedIndex])) {
            throw new \InvalidArgumentException(
                "Invalid suggestion index: {$selectedIndex}. Available: 0-" . (count($suggestions) - 1)
            );
        }

        $selected = $suggestions[$selectedIndex];

        $productName = $selected['name'] ?? $selected['product_name'] ?? 'Unknown Product';
        $brand = $selected['brand'] ?? null;

        $listItem = ListItem::create([
            'shopping_list_id' => $shoppingListId,
            'added_by_user_id' => $item->user_id,
            'product_name' => $productName,
            'product_query' => $brand ? "{$brand} {$productName}" : $productName,
            'target_price' => $selected['typical_price'] ?? null,
            'upc' => $selected['upc'] ?? null,
        ]);

        $item->update([
            'status' => 'accepted',
            'selected_index' => $selectedIndex,
            'added_item_id' => $listItem->id,
        ]);

        Log::info('SmartAddService: item accepted', [
            'queue_item_id' => $item->id,
            'list_item_id' => $listItem->id,
            'selected_index' => $selectedIndex,
        ]);

        return $listItem;
    }

    /**
     * Reject/dismiss a queue item.
     *
     * @param SmartAddQueueItem $item The queue item to dismiss
     */
    public function rejectItem(SmartAddQueueItem $item): void
    {
        $item->update([
            'status' => 'dismissed',
        ]);

        Log::info('SmartAddService: item dismissed', [
            'queue_item_id' => $item->id,
        ]);
    }

    /**
     * Build the vision prompt for product identification from an image.
     */
    private function buildVisionIdentificationPrompt(): string
    {
        return <<<'PROMPT'
        Look at this image and identify the product(s) shown. Provide exactly 5 product suggestions
        that match what you see, ordered from most likely to least likely.

        For each suggestion, provide:
        - name: The specific product name
        - brand: The brand/manufacturer
        - typical_price: The typical retail price in USD (as a number)
        - category: Product category (e.g., "Groceries", "Electronics", "Household")
        - upc: The UPC/barcode number if you can identify it, otherwise null

        Return a JSON array of exactly 5 objects with these keys.
        Return ONLY the JSON array, no other text or markdown formatting.
        PROMPT;
    }

    /**
     * Build the text prompt for product identification from a query.
     */
    private function buildTextIdentificationPrompt(string $query): string
    {
        return <<<PROMPT
        The user is looking for a product described as: "{$query}"

        Provide exactly 5 product suggestions that match this description, ordered from most likely
        to least likely. Consider common brands and products available at major retailers.

        For each suggestion, provide:
        - name: The specific product name
        - brand: The brand/manufacturer
        - typical_price: The typical retail price in USD (as a number)
        - category: Product category (e.g., "Groceries", "Electronics", "Household")
        - upc: The UPC/barcode number if known, otherwise null

        Return a JSON array of exactly 5 objects with these keys.
        Return ONLY the JSON array, no other text or markdown formatting.
        PROMPT;
    }

    /**
     * Build the system prompt for product identification.
     */
    private function buildIdentificationSystemPrompt(): string
    {
        return 'You are a product identification assistant for a shopping list application. '
            . 'You are knowledgeable about consumer products, brands, pricing, and UPC codes. '
            . 'Always return valid JSON arrays. Do not include markdown formatting or code blocks.';
    }

    /**
     * Parse the AI identification response into a structured array.
     */
    private function parseIdentificationResponse(string $response): array
    {
        // Strip any markdown code block wrappers the LLM may have added
        $cleaned = preg_replace('/^```(?:json)?\s*/', '', trim($response));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $parsed = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('SmartAddService: failed to parse identification response', [
                'response' => $response,
                'json_error' => json_last_error_msg(),
            ]);
            return [];
        }

        if (!is_array($parsed)) {
            return [];
        }

        return $parsed;
    }

    /**
     * Create SmartAddQueueItem entries from AI suggestions.
     */
    private function createQueueItems(User $user, array $suggestions, AIJob $aiJob, ?string $imagePath = null): void
    {
        if (empty($suggestions)) {
            return;
        }

        SmartAddQueueItem::create([
            'user_id' => $user->id,
            'ai_job_id' => $aiJob->id,
            'status' => 'ready',
            'product_data' => $suggestions,
            'source' => $imagePath ? 'image' : 'text',
            'source_image_path' => $imagePath,
        ]);
    }

    /**
     * Send a completion notification to the user.
     */
    private function notifyComplete(User $user, int $productCount, string $sourceType, int $jobId): void
    {
        if ($productCount === 0) {
            return;
        }

        try {
            (new SmartAddCompleteNotification(
                $productCount,
                $sourceType,
                $jobId,
            ))->send($user);
        } catch (\Exception $e) {
            Log::warning('SmartAddService: failed to send completion notification', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
