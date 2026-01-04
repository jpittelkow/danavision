<?php

namespace App\Jobs\AI;

use App\Models\AIJob;
use App\Models\ListItem;
use App\Services\AI\AIPriceSearchService;
use Illuminate\Support\Facades\Log;

/**
 * SmartFillJob
 * 
 * Background job for AI-enhanced item detail filling.
 * Uses AI to find product information like images, SKU/UPC, description, and suggested pricing.
 */
class SmartFillJob extends BaseAIJob
{
    /**
     * Process the smart fill job.
     *
     * @param AIJob $aiJob The AIJob model
     * @return array|null The job output data
     */
    protected function process(AIJob $aiJob): ?array
    {
        $inputData = $aiJob->input_data ?? [];
        $itemId = $aiJob->related_item_id;
        $productName = $inputData['product_name'] ?? null;

        if (!$productName) {
            throw new \RuntimeException('No product name provided.');
        }

        $this->updateProgress($aiJob, 10);

        // Get logging service
        $loggingService = $this->getLoggingService();

        if (!$loggingService) {
            throw new \RuntimeException('No AI provider configured. Please set up an AI provider in Settings.');
        }

        // Get the item for existing info
        $item = $itemId ? ListItem::find($itemId) : null;

        $this->updateProgress($aiJob, 20);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        // Build prompt for AI analysis
        $prompt = $this->buildSmartFillPrompt($productName, $item);

        // Query AI for product information
        $response = $loggingService->complete($prompt);
        $aiData = $this->parseSmartFillResponse($response);

        $this->updateProgress($aiJob, 50);

        // Check for cancellation
        if ($this->isCancelled($aiJob)) {
            return ['cancelled' => true];
        }

        // Search for prices to get current market data
        $priceService = AIPriceSearchService::forUser($this->userId);
        $priceService->setAIJobId($aiJob->id);

        $suggestedTargetPrice = null;
        $commonPrice = null;
        $foundImageUrl = $aiData['image_url'] ?? null;
        $providersUsed = [$loggingService->getProviderType()];

        if ($priceService->isWebSearchAvailable()) {
            $searchResult = $priceService->search($productName, [
                'is_generic' => $inputData['is_generic'] ?? ($item->is_generic ?? false),
                'unit_of_measure' => $inputData['unit_of_measure'] ?? ($item->unit_of_measure ?? null),
            ]);

            if ($searchResult->hasResults()) {
                $prices = array_filter(array_column($searchResult->results, 'price'));
                
                if (!empty($prices)) {
                    sort($prices);
                    $count = count($prices);
                    
                    // Use median price as common price
                    $medianIndex = floor($count / 2);
                    $commonPrice = $count % 2 === 0
                        ? ($prices[$medianIndex - 1] + $prices[$medianIndex]) / 2
                        : $prices[$medianIndex];

                    // Suggest target price slightly below median (10% discount)
                    $suggestedTargetPrice = round($commonPrice * 0.9, 2);
                }

                // Get image from first result if AI didn't provide one
                if (!$foundImageUrl) {
                    foreach ($searchResult->results as $priceResult) {
                        if (!empty($priceResult['image_url'])) {
                            $foundImageUrl = $priceResult['image_url'];
                            break;
                        }
                    }
                }

                $providersUsed = array_merge($providersUsed, $searchResult->providersUsed);
            }
        }

        $this->updateProgress($aiJob, 80);

        // Update the item if we have one
        if ($item) {
            $updates = [];
            
            if ($foundImageUrl && empty($item->product_image_url)) {
                $updates['product_image_url'] = $foundImageUrl;
            }
            if (!empty($aiData['sku']) && empty($item->sku)) {
                $updates['sku'] = $aiData['sku'];
            }
            if (!empty($aiData['upc']) && empty($item->upc)) {
                $updates['upc'] = $aiData['upc'];
            }
            if ($aiData['is_generic'] && !$item->is_generic) {
                $updates['is_generic'] = true;
                $updates['unit_of_measure'] = $aiData['unit_of_measure'];
            }

            if (!empty($updates)) {
                $item->update($updates);
            }
        }

        $this->updateProgress($aiJob, 90);

        return [
            'success' => true,
            'product_image_url' => $foundImageUrl,
            'sku' => $aiData['sku'] ?? null,
            'upc' => $aiData['upc'] ?? null,
            'description' => $aiData['description'] ?? null,
            'suggested_target_price' => $suggestedTargetPrice,
            'common_price' => $commonPrice,
            'brand' => $aiData['brand'] ?? null,
            'category' => $aiData['category'] ?? null,
            'is_generic' => $aiData['is_generic'] ?? false,
            'unit_of_measure' => $aiData['unit_of_measure'] ?? null,
            'providers_used' => array_unique($providersUsed),
        ];
    }

    /**
     * Build the prompt for smart fill AI analysis.
     */
    protected function buildSmartFillPrompt(string $productName, ?ListItem $item): string
    {
        $existingInfo = [];
        if ($item) {
            if ($item->sku) {
                $existingInfo[] = "SKU: {$item->sku}";
            }
            if ($item->upc) {
                $existingInfo[] = "UPC: {$item->upc}";
            }
            if ($item->notes) {
                $existingInfo[] = "Notes: {$item->notes}";
            }
        }

        $existingContext = !empty($existingInfo)
            ? "\n\nExisting information about this item:\n" . implode("\n", $existingInfo)
            : '';

        return <<<PROMPT
Analyze this product and provide detailed information: "{$productName}"{$existingContext}

Return a JSON object with the following fields:
{
    "sku": "Product SKU/model number if known",
    "upc": "12-digit UPC barcode if this is a packaged retail product, null for generic items",
    "description": "A helpful 1-2 sentence description",
    "brand": "The brand name if identifiable",
    "category": "Product category",
    "image_url": "A valid product image URL if you know one",
    "is_generic": false,
    "unit_of_measure": null
}

Guidelines:
- SKU: Provide the manufacturer's SKU, model number, or part number if identifiable
- UPC: Only provide for packaged retail products. Generic items do NOT have UPCs - use null
- Description: Write a brief, helpful description
- Brand: Identify the brand if possible
- is_generic: Set to true for items sold by weight/volume/count (produce, meat, dairy)
- unit_of_measure: If is_generic is true, specify the unit (lb, oz, kg, gallon, each, dozen, etc.)

Return ONLY the JSON object, no other text.
PROMPT;
    }

    /**
     * Parse the AI response for smart fill.
     */
    protected function parseSmartFillResponse(?string $response): array
    {
        $defaults = [
            'sku' => null,
            'upc' => null,
            'description' => null,
            'brand' => null,
            'category' => null,
            'image_url' => null,
            'is_generic' => false,
            'unit_of_measure' => null,
        ];

        if (!$response) {
            return $defaults;
        }

        // Try to extract JSON from the response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($json['is_generic'])) {
                    $json['is_generic'] = (bool) $json['is_generic'];
                }
                return array_merge($defaults, array_filter($json, fn($v) => $v !== null && $v !== ''));
            }
        }

        return $defaults;
    }
}
