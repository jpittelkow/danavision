<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Services\AI\AILoggingService;
use App\Services\AI\MultiAIService;
use Illuminate\Support\Facades\Log;

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
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $inputData = $aiJob->input_data ?? [];
        $hasImage = !empty($inputData['image']);
        $query = $inputData['query'] ?? null;

        $this->updateProgress($aiJob, 10);

        // Get logging service for AI calls
        $loggingService = $this->getLoggingService();

        if (!$loggingService) {
            throw new \RuntimeException('No AI provider configured. Please set up an AI provider in Settings.');
        }

        $this->updateProgress($aiJob, 20);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        $results = [];
        $providersUsed = [];

        if ($hasImage) {
            // Image-based identification
            $results = $this->identifyFromImage($inputData, $loggingService, $providersUsed);
        } elseif ($query) {
            // Text-based identification
            $results = $this->identifyFromText($query, $loggingService, $providersUsed);
        } else {
            throw new \RuntimeException('No image or query provided for identification.');
        }

        $this->updateProgress($aiJob, 90);

        return [
            'results' => array_slice($results, 0, 5),
            'providers_used' => $providersUsed,
            'has_image' => $hasImage,
        ];
    }

    /**
     * Identify product from image.
     *
     * @param array $inputData The input data with image
     * @param AILoggingService $loggingService The logging service
     * @param array &$providersUsed Array to track providers used
     * @return array Product suggestions
     */
    protected function identifyFromImage(array $inputData, AILoggingService $loggingService, array &$providersUsed): array
    {
        $imageData = $inputData['image'];
        $context = $inputData['context'] ?? null;

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

        $prompt = $this->buildImageIdentificationPrompt($context);

        $response = $loggingService->analyzeImage($base64, $mimeType, $prompt);
        $providersUsed[] = $loggingService->getProviderType();

        return $this->parseProductSuggestions($response);
    }

    /**
     * Identify product from text query.
     *
     * @param string $query The search query
     * @param AILoggingService $loggingService The logging service
     * @param array &$providersUsed Array to track providers used
     * @return array Product suggestions
     */
    protected function identifyFromText(string $query, AILoggingService $loggingService, array &$providersUsed): array
    {
        $prompt = $this->buildTextIdentificationPrompt($query);

        $response = $loggingService->complete($prompt);
        $providersUsed[] = $loggingService->getProviderType();

        return $this->parseProductSuggestions($response);
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
