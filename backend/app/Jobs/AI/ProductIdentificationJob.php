<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\SmartAddQueueItem;
use App\Services\AI\AILoggingService;
use App\Services\AI\MultiAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ProductIdentificationJob
 * 
 * Background job for identifying products from images or text queries.
 * Returns up to 5 product suggestions with confidence scores.
 */
class ProductIdentificationJob extends BaseAIJob
{
    /**
     * Process the product identification job.
     * Emits detailed progress logs for real-time status monitoring.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $inputData = $aiJob->input_data ?? [];
        $hasImage = !empty($inputData['image']);
        $query = $inputData['query'] ?? null;
        $logs = [];

        // Step 1: Initialize
        $logs[] = 'Initializing AI providers...';
        $this->updateProgress($aiJob, 5, $logs);

        // Get logging service for AI calls
        $loggingService = $this->getLoggingService();

        if (!$loggingService) {
            $logs[] = 'ERROR: No AI provider configured';
            $this->updateProgress($aiJob, 10, $logs);
            throw new \RuntimeException('No AI provider configured. Please set up an AI provider in Settings.');
        }

        $providerType = $loggingService->getProviderType();
        $logs[] = "AI provider initialized: " . ucfirst($providerType);
        $this->updateProgress($aiJob, 15, $logs);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        // Step 2: Analyze input
        if ($hasImage) {
            $logs[] = 'Analyzing image input...';
        } else {
            $logs[] = "Analyzing text query: \"{$query}\"";
        }
        $this->updateProgress($aiJob, 25, $logs);

        $results = [];
        $providersUsed = [];

        // Step 3: Query AI provider(s)
        $logs[] = "Querying AI provider ({$providerType})...";
        $this->updateProgress($aiJob, 40, $logs);

        if ($hasImage) {
            // Image-based identification
            $results = $this->identifyFromImage($inputData, $loggingService, $providersUsed, $aiJob, $logs);
        } elseif ($query) {
            // Text-based identification
            $results = $this->identifyFromText($query, $loggingService, $providersUsed, $aiJob, $logs);
        } else {
            throw new \RuntimeException('No image or query provided for identification.');
        }

        // Step 4: Processing results
        $logs[] = "Provider response received";
        $logs[] = "Found " . count($results) . " potential products";
        $this->updateProgress($aiJob, 80, $logs);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true, 'logs' => $logs];
        }

        // Step 5: Enriching with images (if not already present)
        $logs[] = 'Enriching product data...';
        $this->updateProgress($aiJob, 85, $logs);

        // Add image URLs if AI didn't provide them
        $results = $this->enrichWithImageUrls($results);

        // Step 6: Save to review queue for persistence
        $slicedResults = array_slice($results, 0, 5);
        $queueItem = null;
        
        if (count($slicedResults) > 0) {
            $logs[] = 'Saving to review queue...';
            $this->updateProgress($aiJob, 90, $logs);
            
            $queueItem = $this->saveToQueue(
                $aiJob,
                $slicedResults,
                $hasImage,
                $query,
                $inputData['image'] ?? null,
                $providersUsed
            );
            
            if ($queueItem) {
                $logs[] = 'Results saved to queue (ID: ' . $queueItem->id . ')';
            }
        }

        $logs[] = 'Product identification completed';
        $this->updateProgress($aiJob, 95, $logs);

        return [
            'results' => $slicedResults,
            'providers_used' => $providersUsed,
            'has_image' => $hasImage,
            'logs' => $logs,
            'queue_item_id' => $queueItem?->id,
        ];
    }

    /**
     * Save product identification results to the review queue.
     *
     * @param AIJob $aiJob The AI job
     * @param array $results Product suggestions
     * @param bool $hasImage Whether the source was an image
     * @param string|null $query Text query (if any)
     * @param string|null $imageData Base64 image data (if any)
     * @param array $providersUsed Providers used for identification
     * @return SmartAddQueueItem|null
     */
    protected function saveToQueue(
        AIJob $aiJob,
        array $results,
        bool $hasImage,
        ?string $query,
        ?string $imageData,
        array $providersUsed
    ): ?SmartAddQueueItem {
        try {
            $sourceImagePath = null;
            
            // If we have an image, save it to storage
            if ($hasImage && $imageData) {
                $sourceImagePath = $this->saveImageToStorage($imageData, $this->userId);
            }

            return SmartAddQueueItem::createFromJobResults(
                userId: $this->userId,
                productData: $results,
                sourceType: $hasImage ? SmartAddQueueItem::SOURCE_IMAGE : SmartAddQueueItem::SOURCE_TEXT,
                sourceQuery: $query,
                sourceImagePath: $sourceImagePath,
                aiJobId: $aiJob->id,
                providersUsed: $providersUsed
            );
        } catch (\Exception $e) {
            Log::warning('Failed to save to smart add queue', [
                'ai_job_id' => $aiJob->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Save a base64 image to storage.
     *
     * @param string $imageData Base64 encoded image (data URL)
     * @param int $userId User ID for path organization
     * @return string|null The storage path
     */
    protected function saveImageToStorage(string $imageData, int $userId): ?string
    {
        try {
            $extension = 'jpg';
            $base64 = $imageData;

            if (str_starts_with($imageData, 'data:')) {
                $parts = explode(',', $imageData, 2);
                if (count($parts) === 2) {
                    preg_match('/data:image\/(.*?);base64/', $parts[0], $matches);
                    $extension = $matches[1] ?? 'jpg';
                    if ($extension === 'jpeg') {
                        $extension = 'jpg';
                    }
                    $base64 = $parts[1];
                }
            }

            $decodedImage = base64_decode($base64);
            if ($decodedImage === false) {
                return null;
            }

            $filename = 'smart-add-queue/' . $userId . '/' . Str::uuid() . '.' . $extension;
            Storage::disk('public')->put($filename, $decodedImage);

            return $filename;
        } catch (\Exception $e) {
            Log::warning('Failed to save queue image', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Identify product from image.
     *
     * @param array $inputData The input data with image
     * @param AILoggingService $loggingService The logging service
     * @param array &$providersUsed Array to track providers used
     * @param AIJob $aiJob The AIJob for progress updates
     * @param array &$logs Progress logs array
     * @return array Product suggestions
     */
    protected function identifyFromImage(array $inputData, AILoggingService $loggingService, array &$providersUsed, AIJob $aiJob, array &$logs): array
    {
        $imageData = $inputData['image'];
        $context = $inputData['query'] ?? $inputData['context'] ?? null;

        // Parse base64 data URL
        $mimeType = 'image/jpeg';
        $base64 = $imageData;

        if (str_starts_with($imageData, 'data:')) {
            $parts = explode(',', $imageData, 2);
            if (count($parts) === 2) {
                preg_match('/data:(.*?);base64/', $parts[0], $matches);
                $mimeType = $matches[1] ?? 'image/jpeg';
                $base64 = $parts[1];
            }
        }

        $logs[] = 'Sending image to AI for analysis...';
        $this->updateProgress($aiJob, 50, $logs);

        $prompt = $this->buildImageIdentificationPrompt($context);

        $response = $loggingService->analyzeImage($base64, $mimeType, $prompt);
        $providersUsed[] = $loggingService->getProviderType();

        $logs[] = 'AI analysis complete, parsing results...';
        $this->updateProgress($aiJob, 70, $logs);

        return $this->parseProductSuggestions($response);
    }

    /**
     * Identify product from text query.
     *
     * @param string $query The search query
     * @param AILoggingService $loggingService The logging service
     * @param array &$providersUsed Array to track providers used
     * @param AIJob $aiJob The AIJob for progress updates
     * @param array &$logs Progress logs array
     * @return array Product suggestions
     */
    protected function identifyFromText(string $query, AILoggingService $loggingService, array &$providersUsed, AIJob $aiJob, array &$logs): array
    {
        $logs[] = 'Sending query to AI for product matching...';
        $this->updateProgress($aiJob, 50, $logs);

        $prompt = $this->buildTextIdentificationPrompt($query);

        $response = $loggingService->complete($prompt);
        $providersUsed[] = $loggingService->getProviderType();

        $logs[] = 'AI response received, parsing products...';
        $this->updateProgress($aiJob, 70, $logs);

        return $this->parseProductSuggestions($response);
    }

    /**
     * Enrich product suggestions with image URLs if not already present.
     *
     * @param array $results Product suggestions
     * @return array Enriched product suggestions
     */
    protected function enrichWithImageUrls(array $results): array
    {
        // For now, AI is expected to provide image URLs
        // This method can be extended to fetch images from external sources
        // when AI doesn't provide them
        foreach ($results as $i => $result) {
            if (!empty($result['image_url'])) {
                // Validate URL format
                if (!filter_var($result['image_url'], FILTER_VALIDATE_URL)) {
                    $results[$i]['image_url'] = null;
                }
            }
        }

        return $results;
    }

    /**
     * Build prompt for image-based product identification.
     */
    protected function buildImageIdentificationPrompt(?string $context): string
    {
        $contextPart = $context ? "Additional context from user: {$context}\n\n" : '';

        return <<<PROMPT
{$contextPart}Analyze this product image and identify what product it is. Return up to 5 possible product matches, ranked by confidence.

Return a JSON array with the following format:
[
    {
        "product_name": "Full product name including brand and model",
        "brand": "Brand/manufacturer name",
        "model": "Model number if identifiable",
        "category": "Product category (e.g., Electronics, Groceries, Home & Kitchen)",
        "upc": "12-digit UPC barcode if known, null otherwise",
        "image_url": "Direct URL to a product image from a major retailer or manufacturer, null if unknown",
        "is_generic": false,
        "unit_of_measure": null,
        "confidence": 95
    }
]

Guidelines:
1. Return up to 5 possible matches, ranked by confidence (highest first)
2. Be specific - include brand and model when identifiable
3. Confidence score should be 0-100
4. For branded products, set is_generic: false
5. For generic items (produce, bulk goods), set is_generic: true and unit_of_measure appropriately
6. Generic items do NOT have UPCs - use null
7. For image_url, provide a direct link to a product image from Amazon, Walmart, Target, Best Buy, or manufacturer site if you know one. Use stable CDN URLs. Set to null if unknown.

Only return the JSON array, no other text.
PROMPT;
    }

    /**
     * Build prompt for text-based product identification.
     */
    protected function buildTextIdentificationPrompt(string $query): string
    {
        return <<<PROMPT
Based on this search query, identify what product the user is looking for: "{$query}"

Return up to 5 possible product matches that could match this query, ranked by how likely they are what the user wants.

Return a JSON array with the following format:
[
    {
        "product_name": "Full product name including brand and model",
        "brand": "Brand/manufacturer name",
        "model": "Model number if applicable",
        "category": "Product category (e.g., Electronics, Groceries, Home & Kitchen)",
        "upc": "12-digit UPC barcode if known, null otherwise",
        "image_url": "Direct URL to a product image from a major retailer or manufacturer, null if unknown",
        "is_generic": false,
        "unit_of_measure": null,
        "confidence": 95
    }
]

Guidelines:
1. Return up to 5 possible matches, ranked by confidence (highest first)
2. Think about what products the user might be searching for
3. Include popular/common variants if the query is general
4. For branded products, set is_generic: false
5. For generic items (produce, bulk goods), set is_generic: true and unit_of_measure appropriately
6. Generic items do NOT have UPCs - use null
7. For image_url, provide a direct link to a product image from Amazon, Walmart, Target, Best Buy, or manufacturer site if you know one. Use stable CDN URLs. Set to null if unknown.

Only return the JSON array, no other text.
PROMPT;
    }

    /**
     * Parse product suggestions from AI response.
     */
    protected function parseProductSuggestions(string $response): array
    {
        $results = [];

        // Try to extract JSON array from the response
        if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                foreach ($parsed as $item) {
                    if (isset($item['product_name']) || isset($item['brand'])) {
                        $results[] = [
                            'product_name' => $item['product_name'] ?? ($item['brand'] . ' ' . ($item['model'] ?? '')),
                            'brand' => $item['brand'] ?? null,
                            'model' => $item['model'] ?? null,
                            'category' => $item['category'] ?? null,
                            'upc' => $item['upc'] ?? null,
                            'image_url' => $item['image_url'] ?? null,
                            'is_generic' => (bool) ($item['is_generic'] ?? false),
                            'unit_of_measure' => $item['unit_of_measure'] ?? null,
                            'confidence' => (int) ($item['confidence'] ?? 50),
                        ];
                    }
                }
            }
        }

        // Sort by confidence descending
        usort($results, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $results;
    }
}
