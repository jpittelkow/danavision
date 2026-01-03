<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIService;

class ProductImageAnalyzer
{
    protected AIService $ai;

    public function __construct(AIService $ai)
    {
        $this->ai = $ai;
    }

    /**
     * Analyze a product image and identify it.
     */
    public function analyze(string $base64Image, string $mimeType): ProductIdentification
    {
        $prompt = <<<PROMPT
Analyze this product image and provide the following information in JSON format:
{
    "product_name": "The specific product name",
    "brand": "The brand name if visible",
    "model": "The model number if visible",
    "category": "The product category (e.g., Electronics, Kitchen, Clothing)",
    "search_terms": ["array", "of", "suggested", "search", "terms"],
    "confidence": 85
}

Be specific and accurate. If you can't determine something with confidence, use null.
The confidence score should be 0-100 based on how certain you are about the identification.
Only return the JSON, no other text.
PROMPT;

        $response = $this->ai->analyzeImage($base64Image, $mimeType, $prompt);

        // Parse the JSON response
        $data = $this->parseJsonResponse($response);

        return new ProductIdentification(
            productName: $data['product_name'] ?? 'Unknown Product',
            brand: $data['brand'] ?? null,
            model: $data['model'] ?? null,
            category: $data['category'] ?? null,
            searchTerms: $data['search_terms'] ?? [],
            confidence: $data['confidence'] ?? 0,
        );
    }

    /**
     * Parse JSON from AI response.
     */
    protected function parseJsonResponse(string $response): array
    {
        // Try to extract JSON from the response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        // If parsing fails, return empty array
        return [];
    }
}

class ProductIdentification
{
    public function __construct(
        public string $productName,
        public ?string $brand,
        public ?string $model,
        public ?string $category,
        public array $searchTerms,
        public int $confidence,
    ) {}

    public function toArray(): array
    {
        return [
            'product_name' => $this->productName,
            'brand' => $this->brand,
            'model' => $this->model,
            'category' => $this->category,
            'search_terms' => $this->searchTerms,
            'confidence' => $this->confidence,
        ];
    }
}
